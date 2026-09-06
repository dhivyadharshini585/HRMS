<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReportController extends Controller
{
    /**
     * Resolve scoped query and single target employee ID based on user RBAC.
     */
    protected function resolveScopedQuery(Request $request)
    {
        $user = $request->user();

        // Finance/Payroll Admin explicitly forbidden from leave reports
        if ($user->hasRole('Finance/Payroll Admin') && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return ['error' => response()->json(['message' => 'Unauthorized action. Finance/Payroll Admin does not have access to leave reports.'], 403)];
        }

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $employee = $user->employee;
        $targetEmployeeId = null;

        $query = LeaveRequest::with(['employee.department', 'leaveType', 'manager', 'reviewer']);

        if ($isHR) {
            // Company-wide access
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
                $targetEmployeeId = (int) $request->employee_id;
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

            $allowedEmployeeIds = Employee::where('manager_id', $employee->id)
                ->pluck('id')
                ->push($employee->id)
                ->toArray();

            if ($request->filled('employee_id')) {
                if (!in_array((int)$request->employee_id, $allowedEmployeeIds)) {
                    return ['error' => response()->json(['message' => 'Unauthorized. You can only view leave reports for yourself and your direct reports.'], 403)];
                }
                $query->where('employee_id', $request->employee_id);
                $targetEmployeeId = (int) $request->employee_id;
            } else {
                $query->whereIn('employee_id', $allowedEmployeeIds);
            }
        } elseif ($user->hasRole('Employee')) {
            if (!$employee) {
                return ['error' => response()->json(['message' => 'No linked employee profile found for user.'], 400)];
            }

            if ($request->filled('employee_id') && (int)$request->employee_id !== $employee->id) {
                return ['error' => response()->json(['message' => 'Unauthorized. You can only view your own leave report.'], 403)];
            }
            $query->where('employee_id', $employee->id);
            $targetEmployeeId = $employee->id;
        } else {
            return ['error' => response()->json(['message' => 'Unauthorized action.'], 403)];
        }

        // Date range filtering (against from_date/to_date)
        if ($request->filled('date_from')) {
            $query->whereDate('from_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('to_date', '<=', $request->date_to);
        }

        // Leave type filter
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return [
            'query' => $query,
            'target_employee_id' => $targetEmployeeId,
        ];
    }

    /**
     * Validate incoming report filter parameters.
     */
    protected function validateFilters(Request $request): void
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'status' => 'nullable|in:Pending,Manager Approved,Approved,Rejected,Cancelled',
            'employee_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
    }

    /**
     * Get paginated leave report with summary and balances.
     */
    public function index(Request $request)
    {
        $this->validateFilters($request);

        $resolved = $this->resolveScopedQuery($request);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $query = $resolved['query'];
        $targetEmployeeId = $resolved['target_employee_id'];

        // Calculate summary metrics
        $summaryQuery = clone $query;
        $totalRequests = $summaryQuery->count();
        $totalDays = (float) (clone $summaryQuery)->sum('number_of_days');

        $statusCounts = [
            'Pending' => (clone $summaryQuery)->where('status', 'Pending')->count(),
            'Manager Approved' => (clone $summaryQuery)->where('status', 'Manager Approved')->count(),
            'Approved' => (clone $summaryQuery)->where('status', 'Approved')->count(),
            'Rejected' => (clone $summaryQuery)->where('status', 'Rejected')->count(),
            'Cancelled' => (clone $summaryQuery)->where('status', 'Cancelled')->count(),
        ];

        // Leave balances breakdown if scoped to a specific employee
        $balances = [];
        if ($targetEmployeeId) {
            $year = Carbon::now()->year;
            $balances = LeaveBalance::with('leaveType')
                ->where('employee_id', $targetEmployeeId)
                ->where('year', $year)
                ->get()
                ->map(fn($b) => [
                    'leave_type_id' => $b->leave_type_id,
                    'leave_type' => $b->leaveType?->name,
                    'allocated_days' => $b->allocated_days,
                    'used_days' => $b->used_days,
                    'remaining_days' => $b->remaining_days,
                ]);
        }

        $perPage = $request->input('per_page', 15);
        $records = $query->orderBy('from_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'current_page' => $records->currentPage(),
            'last_page' => $records->lastPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
            'summary' => [
                'total_requests' => $totalRequests,
                'total_days' => $totalDays,
                'status_counts' => $statusCounts,
                'balances' => $balances,
            ]
        ]);
    }

    /**
     * Stream CSV export of leave report.
     */
    public function export(Request $request)
    {
        $this->validateFilters($request);

        $resolved = $this->resolveScopedQuery($request);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $query = $resolved['query'];
        $records = $query->orderBy('from_date', 'desc')->get();

        $filename = 'leave_report_' . Carbon::now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Request ID',
                'Employee Code',
                'Employee Name',
                'Leave Type',
                'From Date',
                'To Date',
                'Days',
                'Reason',
                'Status',
                'Manager Remarks',
                'HR Remarks',
            ]);

            foreach ($records as $row) {
                $empName = $row->employee ? "{$row->employee->first_name} {$row->employee->last_name}" : 'N/A';
                $fromDate = Carbon::parse($row->from_date)->toDateString();
                $toDate = Carbon::parse($row->to_date)->toDateString();

                fputcsv($file, [
                    $row->id,
                    $row->employee?->employee_code ?? 'N/A',
                    $empName,
                    $row->leaveType?->name ?? 'N/A',
                    $fromDate,
                    $toDate,
                    $row->number_of_days,
                    $row->reason,
                    $row->status,
                    $row->manager_remarks ?? '',
                    $row->hr_remarks ?? '',
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
