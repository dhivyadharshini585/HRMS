<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    /**
     * Resolve scoped employee IDs based on user RBAC.
     */
    protected function resolveScopedQuery(Request $request)
    {
        $user = $request->user();

        // 1. Finance/Payroll Admin explicitly forbidden from attendance reports
        if ($user->hasRole('Finance/Payroll Admin') && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return ['error' => response()->json(['message' => 'Unauthorized action. Finance/Payroll Admin does not have access to attendance reports.'], 403)];
        }

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $employee = $user->employee;

        // Base query
        $query = Attendance::with(['employee.department', 'employee.designation']);

        if ($isHR) {
            // Company-wide access
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->filled('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
        } elseif ($user->hasRole('Manager')) {
            if (!$employee) {
                return ['error' => response()->json(['message' => 'No linked employee profile found for manager.'], 400)];
            }

            // Manager: self + direct reports
            $allowedEmployeeIds = Employee::where('manager_id', $employee->id)
                ->pluck('id')
                ->push($employee->id)
                ->toArray();

            if ($request->filled('employee_id')) {
                if (!in_array((int)$request->employee_id, $allowedEmployeeIds)) {
                    return ['error' => response()->json(['message' => 'Unauthorized. You can only view attendance reports for yourself and your direct reports.'], 403)];
                }
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->whereIn('employee_id', $allowedEmployeeIds);
            }
        } elseif ($user->hasRole('Employee')) {
            if (!$employee) {
                return ['error' => response()->json(['message' => 'No linked employee profile found for user.'], 400)];
            }

            // Employee: strictly own records
            if ($request->filled('employee_id') && (int)$request->employee_id !== $employee->id) {
                return ['error' => response()->json(['message' => 'Unauthorized. You can only view your own attendance report.'], 403)];
            }
            $query->where('employee_id', $employee->id);
        } else {
            return ['error' => response()->json(['message' => 'Unauthorized action.'], 403)];
        }

        // Date range filtering
        if ($request->filled('date_from')) {
            $query->whereDate('attendance_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('attendance_date', '<=', $request->date_to);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ['query' => $query];
    }

    /**
     * Validate incoming report filter parameters.
     */
    protected function validateFilters(Request $request): void
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'status' => 'nullable|in:Present,Late,Half Day',
            'employee_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
    }

    /**
     * Get paginated attendance report with summary.
     */
    public function index(Request $request)
    {
        $this->validateFilters($request);

        $resolved = $this->resolveScopedQuery($request);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $query = $resolved['query'];

        // Clone query for calculating overall summary metrics
        $summaryQuery = clone $query;
        $totalDays = $summaryQuery->count();
        $presentDays = (clone $summaryQuery)->where('status', 'Present')->count();
        $lateDays = (clone $summaryQuery)->where('status', 'Late')->count();
        $halfDays = (clone $summaryQuery)->where('status', 'Half Day')->count();
        $totalWorkingMinutes = (int) (clone $summaryQuery)->sum('working_minutes');
        $totalOvertimeMinutes = (int) (clone $summaryQuery)->sum('overtime_minutes');

        $perPage = $request->input('per_page', 15);
        $records = $query->orderBy('attendance_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'current_page' => $records->currentPage(),
            'last_page' => $records->lastPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
            'summary' => [
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'late_days' => $lateDays,
                'half_days' => $halfDays,
                'total_working_minutes' => $totalWorkingMinutes,
                'total_working_hours' => round($totalWorkingMinutes / 60, 2),
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'total_overtime_hours' => round($totalOvertimeMinutes / 60, 2),
            ]
        ]);
    }

    /**
     * Stream CSV export of attendance report.
     */
    public function export(Request $request)
    {
        $this->validateFilters($request);

        $resolved = $this->resolveScopedQuery($request);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $query = $resolved['query'];
        $records = $query->orderBy('attendance_date', 'desc')->get();

        $filename = 'attendance_report_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            // CSV Headers
            fputcsv($file, [
                'Employee Code',
                'Employee Name',
                'Department',
                'Date',
                'Check In',
                'Check Out',
                'Working Minutes',
                'Status',
                'Overtime (Minutes)',
                'Remarks'
            ]);

            foreach ($records as $row) {
                $empName = $row->employee ? "{$row->employee->first_name} {$row->employee->last_name}" : 'N/A';
                $deptName = $row->employee?->department?->name ?? 'N/A';
                $checkIn = $row->check_in ? Carbon::parse($row->check_in)->format('Y-m-d H:i:s') : 'N/A';
                $checkOut = $row->check_out ? Carbon::parse($row->check_out)->format('Y-m-d H:i:s') : 'N/A';

                fputcsv($file, [
                    $row->employee?->employee_code ?? 'N/A',
                    $empName,
                    $deptName,
                    $row->attendance_date,
                    $checkIn,
                    $checkOut,
                    $row->working_minutes ?? 0,
                    $row->status,
                    $row->overtime_minutes ?? 0,
                    $row->remarks ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
