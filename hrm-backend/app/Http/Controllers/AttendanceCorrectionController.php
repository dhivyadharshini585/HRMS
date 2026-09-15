<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Services\AuditService;
use App\Notifications\AttendanceCorrectionSubmittedNotification;
use App\Notifications\AttendanceCorrectionApprovedNotification;
use App\Notifications\AttendanceCorrectionRejectedNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('attendance.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = AttendanceCorrection::with(['attendance', 'employee', 'reviewer']);

        if ($user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive', 'Finance/Payroll Admin'])) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
        } elseif ($user->hasRole('Manager')) {
            $managerEmp = $user->employee;
            if (!$managerEmp) {
                return response()->json(['data' => [], 'total' => 0]);
            }
            $teamEmployeeIds = Employee::where('manager_id', $managerEmp->id)
                ->pluck('id'); // Managers don't approve their own corrections

            $query->whereIn('employee_id', $teamEmployeeIds);

            if ($request->filled('employee_id')) {
                if (in_array((int) $request->input('employee_id'), $teamEmployeeIds->toArray(), true)) {
                    $query->where('employee_id', $request->input('employee_id'));
                } else {
                    return response()->json(['message' => 'Unauthorized access.'], 403);
                }
            }
        } else {
            $emp = $user->employee;
            if (!$emp) {
                return response()->json(['data' => [], 'total' => 0]);
            }
            $query->where('employee_id', $emp->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->can('attendance.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $employee = $user->employee;
        if (!$employee) {
            return response()->json(['message' => 'No employee record found.'], 400);
        }

        $request->validate([
            'attendance_id' => 'required|exists:attendances,id',
            'requested_check_in' => 'nullable|date',
            'requested_check_out' => 'nullable|date',
            'reason' => 'required|string',
        ]);

        $attendance = Attendance::where('id', $request->attendance_id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'Attendance record not found or does not belong to you.'], 404);
        }

        // Prevent duplicate pending corrections
        $existing = AttendanceCorrection::where('attendance_id', $attendance->id)
            ->where('status', 'Pending')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'A pending correction request already exists for this attendance record.'], 422);
        }

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $employee->id,
            'requested_check_in' => $request->requested_check_in,
            'requested_check_out' => $request->requested_check_out,
            'reason' => $request->reason,
            'status' => 'Pending',
        ]);

        AuditService::logModelChange('attendance_correction.submitted', $correction, [], $correction->toArray(), 'Submitted attendance correction');

        // Notify manager
        if ($employee->manager && $employee->manager->user) {
            $employee->manager->user->notify(new AttendanceCorrectionSubmittedNotification($correction));
        }

        return response()->json(['message' => 'Correction request submitted successfully.', 'correction' => $correction], 201);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $correction = AttendanceCorrection::with(['attendance', 'employee.user'])->findOrFail($id);

        if (!$this->canReview($user, $correction)) {
            return response()->json(['message' => 'Unauthorized to approve this correction.'], 403);
        }

        if ($correction->status !== 'Pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $attendance = $correction->attendance;
        $oldAttendance = $attendance->toArray();

        $checkIn = $correction->requested_check_in ?? $attendance->check_in;
        $checkOut = $correction->requested_check_out ?? $attendance->check_out;

        $updateData = [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ];

        // Recalculate metrics if checkout exists
        if ($checkIn && $checkOut) {
            $shift = $attendance->employee->currentShift();
            if ($shift) {
                $metrics = AttendanceCalculationService::calculateMetrics(
                    $checkIn,
                    $checkOut,
                    $shift,
                    $attendance->attendance_date->toDateString()
                );

                $updateData['working_minutes'] = $metrics['working_minutes'];
                $updateData['status'] = $metrics['status'];
                $updateData['overtime_minutes'] = $metrics['overtime_minutes'];
                $updateData['early_exit_minutes'] = $metrics['early_exit_minutes'];

                if ($metrics['overtime_minutes'] > 0) {
                     $updateData['remarks'] = trim($attendance->remarks . ' (Overtime: ' . $metrics['overtime_minutes'] . ' mins) (Corrected)');
                } else {
                     $updateData['remarks'] = trim($attendance->remarks . ' (Corrected)');
                }
            }
        }

        $attendance->update($updateData);

        $correction->update([
            'status' => 'Approved',
            'reviewed_by' => $user->id,
            'remarks' => $request->input('remarks'),
        ]);

        AuditService::logModelChange('attendance_correction.approved', $correction, ['status' => 'Pending'], ['status' => 'Approved'], 'Approved attendance correction');
        AuditService::logModelChange('attendance.updated_by_correction', $attendance, $oldAttendance, $attendance->toArray(), 'Attendance updated via approved correction');

        if ($correction->employee->user) {
            $correction->employee->user->notify(new AttendanceCorrectionApprovedNotification($correction));
        }

        return response()->json(['message' => 'Correction approved successfully.', 'attendance' => $attendance->fresh()]);
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $correction = AttendanceCorrection::with('employee.user')->findOrFail($id);

        if (!$this->canReview($user, $correction)) {
            return response()->json(['message' => 'Unauthorized to reject this correction.'], 403);
        }

        if ($correction->status !== 'Pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $request->validate([
            'remarks' => 'required|string',
        ]);

        $correction->update([
            'status' => 'Rejected',
            'reviewed_by' => $user->id,
            'remarks' => $request->remarks,
        ]);

        AuditService::logModelChange('attendance_correction.rejected', $correction, ['status' => 'Pending'], ['status' => 'Rejected'], 'Rejected attendance correction');

        if ($correction->employee->user) {
            $correction->employee->user->notify(new AttendanceCorrectionRejectedNotification($correction));
        }

        return response()->json(['message' => 'Correction rejected successfully.']);
    }

    private function canReview($user, $correction)
    {
        if ($user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            return true;
        }

        if ($user->hasRole('Manager')) {
            $managerEmp = $user->employee;
            if ($managerEmp && $correction->employee->manager_id === $managerEmp->id) {
                return true; // Can review direct reports
            }
        }

        return false; // Cannot review own correction or others'
    }
}
