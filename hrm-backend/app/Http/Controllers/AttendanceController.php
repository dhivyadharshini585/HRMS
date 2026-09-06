<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records with scope-based authorization.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('attendance.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = Attendance::with(['employee:id,first_name,last_name,employee_code,department_id,designation_id']);

        // Scope resolution
        if ($user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive', 'Finance/Payroll Admin'])) {
            // Full visibility - optional filters
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
        } elseif ($user->hasRole('Manager')) {
            $managerEmp = $user->employee;
            if (!$managerEmp) {
                return response()->json(['data' => [], 'total' => 0]);
            }
            $teamEmployeeIds = Employee::where('manager_id', $managerEmp->id)
                ->pluck('id')
                ->push($managerEmp->id);

            $query->whereIn('employee_id', $teamEmployeeIds);

            if ($request->filled('employee_id')) {
                if (in_array((int) $request->input('employee_id'), $teamEmployeeIds->toArray(), true)) {
                    $query->where('employee_id', $request->input('employee_id'));
                } else {
                    return response()->json(['message' => 'Unauthorized access to target employee.'], 403);
                }
            }
        } else {
            // Normal Employee - strictly restricted to own employee record
            $emp = $user->employee;
            if (!$emp) {
                return response()->json(['data' => [], 'total' => 0]);
            }
            $query->where('employee_id', $emp->id);
        }

        // Common Filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->input('date'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('attendance_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('attendance_date', '<=', $request->input('date_to'));
        }

        $logs = $query->orderByDesc('attendance_date')->orderByDesc('created_at')->paginate(15);

        return response()->json($logs);
    }

    /**
     * Get today's attendance state for the authenticated employee.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['today_attendance' => null]);
        }

        $now = Carbon::now();
        $shift = $employee->currentShift();

        $today = $now->toDateString();
        
        // Handle night shift thresholding for 'today' retrieval
        if ($shift && $shift->start_time > $shift->end_time) {
            $shiftStartTime = Carbon::parse($shift->start_time);
            $shiftEndTime = Carbon::parse($shift->end_time)->addDay();
            $shiftEndWithBuffer = $shiftEndTime->copy()->addHours(4); // e.g. 07:00 AM + 4 hrs = 11:00 AM
            
            // If it's early morning, they are probably checking out for yesterday's shift
            if ($now->format('H:i:s') < $shiftEndWithBuffer->format('H:i:s')) {
                $today = $now->copy()->subDay()->toDateString();
            }
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        return response()->json([
            'today_attendance' => $attendance,
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Check in for today (Employee self-action).
     */
    public function checkIn(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'No linked employee profile found for user.'], 400);
        }

        $now = Carbon::now();
        $shift = $employee->currentShift();
        
        if (!$shift) {
            return response()->json(['message' => 'No shift assigned. Cannot check in.'], 422);
        }

        $attendanceDate = $now->toDateString();
        $isNightShift = $shift->start_time > $shift->end_time;
        
        // Determine the logical attendance date for night shifts
        if ($isNightShift) {
            $shiftEndWithBuffer = Carbon::parse($shift->end_time)->addHours(4)->format('H:i:s');
            // If checking in between 00:00:00 and 07:00 (or buffer), it belongs to previous day's shift
            if ($now->format('H:i:s') <= $shiftEndWithBuffer) {
                $attendanceDate = $now->copy()->subDay()->toDateString();
            }
        }

        // Check if attendance record exists for the logical date
        $existing = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate)
            ->first();

        if ($existing && $existing->check_in !== null) {
            return response()->json(['message' => 'Already checked in for today.'], 422);
        }

        // Determine status upon check-in based on shift rules
        // Create a Carbon instance for the expected check-in time on the attendanceDate
        $dateStr = Carbon::parse($attendanceDate)->toDateString();
        $expectedCheckIn = Carbon::parse($dateStr . ' ' . $shift->start_time);
        
        // Add grace period
        $lateThreshold = $expectedCheckIn->copy()->addMinutes($shift->grace_period_minutes ?? 0);
        
        $status = ($now->greaterThan($lateThreshold)) ? 'Late' : 'Present';

        if ($existing) {
            $existing->update([
                'check_in' => $now,
                'status' => $status,
                'remarks' => $request->input('remarks', $existing->remarks),
            ]);
            $attendance = $existing;
        } else {
            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate,
                'check_in' => $now,
                'status' => $status,
                'remarks' => $request->input('remarks'),
            ]);
        }

        AuditService::logModelChange('attendance.check_in', $attendance, [], $attendance->toArray(), "Checked in at {$now->toTimeString()}");

        return response()->json([
            'message' => 'Checked in successfully.',
            'attendance' => $attendance->fresh(),
        ], 201);
    }

    /**
     * Check out for today (Employee self-action).
     */
    public function checkOut(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'No linked employee profile found for user.'], 400);
        }

        $now = Carbon::now();
        $shift = $employee->currentShift();
        
        if (!$shift) {
            return response()->json(['message' => 'No shift assigned. Cannot check out.'], 422);
        }

        $attendanceDate = $now->toDateString();
        $isNightShift = $shift->start_time > $shift->end_time;
        
        if ($isNightShift) {
            $shiftEndWithBuffer = Carbon::parse($shift->end_time)->addHours(4)->format('H:i:s');
            if ($now->format('H:i:s') <= $shiftEndWithBuffer) {
                $attendanceDate = $now->copy()->subDay()->toDateString();
            }
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate)
            ->first();

        if (!$attendance || $attendance->check_in === null) {
            // Maybe they checked in before midnight, and are checking out after 07:00 AM?
            // Fallback to checking the most recent incomplete attendance
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereNull('check_out')
                ->whereNotNull('check_in')
                ->orderByDesc('attendance_date')
                ->first();
                
            if (!$attendance) {
                return response()->json(['message' => 'Cannot check out before checking in.'], 422);
            }
        }

        if ($attendance->check_out !== null) {
            return response()->json(['message' => 'Already checked out for today.'], 422);
        }

        $oldValues = $attendance->toArray();

        // Calculate duration & final deterministic status
        $checkIn = Carbon::parse($attendance->check_in);
        $workingMinutes = (int) $checkIn->diffInMinutes($now);

        // Expected check-in time for Late logic recalculation if needed
        $dateStr = Carbon::parse($attendance->attendance_date)->toDateString();
        $expectedCheckIn = Carbon::parse($dateStr . ' ' . $shift->start_time);
        $lateThreshold = $expectedCheckIn->copy()->addMinutes($shift->grace_period_minutes ?? 0);

        // Status calculation rule:
        // 1. working_minutes < 240 => Half Day
        // 2. otherwise check_in > lateThreshold => Late
        // 3. otherwise => Present
        if ($workingMinutes < 240) {
            $finalStatus = 'Half Day';
        } elseif ($checkIn->greaterThan($lateThreshold)) {
            $finalStatus = 'Late';
        } else {
            $finalStatus = 'Present';
        }

        // Overtime calculation
        // Ensure overtime logic is safe and only works if overtime is enabled on the shift
        $overtimeMinutes = 0;
        if ($shift->overtime_enabled && $shift->overtime_threshold_minutes !== null) {
            // Expected end time
            $expectedEndTime = Carbon::parse($dateStr . ' ' . $shift->end_time);
            if ($isNightShift) {
                $expectedEndTime->addDay();
            }
            
            // Difference between actual checkout and expected end time
            if ($now->greaterThan($expectedEndTime)) {
                $extraMinutes = (int) $expectedEndTime->diffInMinutes($now);
                if ($extraMinutes >= $shift->overtime_threshold_minutes) {
                    $overtimeMinutes = $extraMinutes;
                }
            }
        }

        $attendance->update([
            'check_out' => $now,
            'working_minutes' => $workingMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'status' => $finalStatus,
            'remarks' => $request->input('remarks', $attendance->remarks),
        ]);
        
        if ($overtimeMinutes > 0) {
             $attendance->update(['remarks' => trim($attendance->remarks . ' (Overtime: ' . $overtimeMinutes . ' mins)')]);
        }

        $fresh = $attendance->fresh();

        AuditService::logModelChange('attendance.check_out', $fresh, $oldValues, $fresh->toArray(), "Checked out at {$now->toTimeString()}");

        return response()->json([
            'message' => 'Checked out successfully.',
            'attendance' => $fresh,
        ], 200);
    }
}
