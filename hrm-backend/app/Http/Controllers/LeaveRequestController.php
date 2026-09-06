<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    /**
     * Display a listing of leave requests based on user role and permissions.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('leave.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = LeaveRequest::with(['employee.department', 'employee.designation', 'leaveType', 'manager', 'reviewer']);

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $isManager = $user->hasRole('Manager');
        $employeeId = $user->employee?->id;

        if ($request->filled('employee_id')) {
            // Target specific employee if authorized
            $targetEmpId = (int) $request->input('employee_id');
            if (!$isHR && $employeeId !== $targetEmpId) {
                // Check if target employee is direct report
                $targetEmp = Employee::find($targetEmpId);
                if (!$targetEmp || $targetEmp->manager_id !== $employeeId) {
                    return response()->json(['message' => 'Unauthorized action.'], 403);
                }
            }
            $query->where('employee_id', $targetEmpId);
        } elseif (!$isHR) {
            if ($isManager) {
                // Manager views own requests + direct reports' requests
                $directReportIds = Employee::where('manager_id', $employeeId)->pluck('id')->toArray();
                $allowedIds = array_merge([$employeeId], $directReportIds);
                $query->whereIn('employee_id', array_filter($allowedIds));
            } else {
                // Employee views only own requests
                if (!$employeeId) {
                    return response()->json(['data' => []]);
                }
                $query->where('employee_id', $employeeId);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->input('leave_type_id'));
        }

        $requests = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

        return response()->json($requests);
    }

    /**
     * Store a newly created leave request.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->can('leave.apply')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $employee = $user->employee;
        if (!$employee) {
            return response()->json(['message' => 'No linked employee profile found for user.'], 400);
        }

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:1000',
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
        if (!$leaveType->is_active) {
            return response()->json(['message' => 'Selected leave type is inactive.'], 422);
        }

        // Calculate days server-side
        $fromDate = Carbon::parse($validated['from_date']);
        $toDate = Carbon::parse($validated['to_date']);
        $numberOfDays = (float) ($fromDate->diffInDays($toDate) + 1);

        // Derive manager_id server-side from employee profile
        $managerId = $employee->manager_id;

        // Check active overlapping leave requests (Pending, Manager Approved, Approved)
        $overlap = LeaveRequest::where('employee_id', $employee->id)
            ->whereIn('status', ['Pending', 'Manager Approved', 'Approved'])
            ->where(function ($q) use ($validated) {
                $q->whereBetween('from_date', [$validated['from_date'], $validated['to_date']])
                  ->orWhereBetween('to_date', [$validated['from_date'], $validated['to_date']])
                  ->orWhere(function ($sub) use ($validated) {
                      $sub->where('from_date', '<=', $validated['from_date'])
                          ->where('to_date', '>=', $validated['to_date']);
                  });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Date range overlaps with an existing active leave request.',
                'errors' => ['from_date' => ['Date range overlaps with an existing active leave request.']]
            ], 422);
        }

        // Check balance for paid leave
        $year = $fromDate->year;
        if (!$leaveType->is_unpaid) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->first();

            if (!$balance || $balance->remaining_days < $numberOfDays) {
                $available = $balance ? $balance->remaining_days : 0;
                return response()->json([
                    'message' => "Insufficient leave balance. Required: {$numberOfDays} days, Available: {$available} days.",
                    'errors' => ['leave_type_id' => ["Insufficient leave balance. Available: {$available} days."]]
                ], 422);
            }
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'number_of_days' => $numberOfDays,
            'reason' => $validated['reason'],
            'status' => 'Pending',
            'manager_id' => $managerId,
        ]);

        $leaveRequest->load(['employee', 'leaveType']);

        AuditService::logModelChange('leave_request.created', $leaveRequest, [], $leaveRequest->toArray(), "Submitted leave request #{$leaveRequest->id} for {$numberOfDays} days");

        // Dispatch notification to assigned manager
        if ($managerId) {
            $managerEmp = Employee::find($managerId);
            if ($managerEmp && $managerEmp->user) {
                $managerEmp->user->notify(new \App\Notifications\LeaveRequestSubmittedNotification($leaveRequest));
            }
        }

        return response()->json($leaveRequest, 201);
    }

    /**
     * Display the specified leave request.
     */
    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $isOwner = $employeeId === $leaveRequest->employee_id;
        $isManager = $employeeId === $leaveRequest->employee->manager_id;

        if (!$isHR && !$isOwner && !$isManager) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $leaveRequest->load(['employee.department', 'employee.designation', 'leaveType', 'manager', 'reviewer']);

        return response()->json($leaveRequest);
    }

    /**
     * Step 1: Manager approval (Pending -> Manager Approved).
     */
    public function managerApprove(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        // Employee CANNOT approve their own request
        if ($employeeId && $employeeId === $leaveRequest->employee_id) {
            return response()->json(['message' => 'Employees cannot approve their own leave requests.'], 403);
        }

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $isManagerOfEmployee = $employeeId && $leaveRequest->employee->manager_id === $employeeId;

        if (!$isHR && !$isManagerOfEmployee) {
            return response()->json(['message' => 'You are not authorized to approve leave requests for this employee.'], 403);
        }

        // Strict state machine check
        if ($leaveRequest->status !== 'Pending') {
            return response()->json(['message' => "Cannot perform manager approval. Request status must be Pending, currently: {$leaveRequest->status}."], 422);
        }

        $oldValues = $leaveRequest->toArray();
        $leaveRequest->update([
            'status' => 'Manager Approved',
            'manager_id' => $employeeId ?: $leaveRequest->manager_id,
            'manager_remarks' => $request->input('manager_remarks'),
        ]);

        $fresh = $leaveRequest->fresh(['employee', 'leaveType', 'manager']);

        AuditService::logModelChange('leave_request.manager_approved', $fresh, $oldValues, $fresh->toArray(), "Manager approved leave request #{$fresh->id}");

        // Dispatch notifications on successful state transition:
        // 1. Notify employee
        if ($fresh->employee?->user) {
            $fresh->employee->user->notify(new \App\Notifications\LeaveRequestManagerApprovedNotification($fresh, 'employee'));
        }
        // 2. Notify HR Admins
        $hrUsers = User::role(['Super Admin', 'HR Admin', 'HR Executive'])->get();
        foreach ($hrUsers as $hrUser) {
            $hrUser->notify(new \App\Notifications\LeaveRequestManagerApprovedNotification($fresh, 'hr'));
        }

        return response()->json([
            'message' => 'Leave request approved by manager successfully.',
            'leave_request' => $fresh,
        ]);
    }

    /**
     * Step 2: HR Final Approval & Atomic Balance Deduction (Manager Approved -> Approved).
     */
    public function hrApprove(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        // Must be HR Admin, HR Executive, or Super Admin
        if (!$user->can('leave.approve') || !$user->hasRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            return response()->json(['message' => 'Unauthorized action. Only authorized HR personnel can perform final leave approval.'], 403);
        }

        // HR cannot directly approve a Pending request
        if ($leaveRequest->status !== 'Manager Approved') {
            return response()->json(['message' => "HR final approval requires request status to be Manager Approved. Current status: {$leaveRequest->status}."], 422);
        }

        try {
            $approvedRequest = DB::transaction(function () use ($leaveRequest, $user, $request) {
                // Lock leave_requests row for update
                $fresh = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->first();

                if ($fresh->status !== 'Manager Approved') {
                    throw new \Exception('Request is no longer pending HR approval.');
                }

                $leaveType = $fresh->leaveType;
                $year = Carbon::parse($fresh->from_date)->year;

                // Deduct balance atomically if paid leave
                if (!$leaveType->is_unpaid) {
                    $balance = LeaveBalance::where('employee_id', $fresh->employee_id)
                        ->where('leave_type_id', $fresh->leave_type_id)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->first();

                    if (!$balance || $balance->remaining_days < $fresh->number_of_days) {
                        throw new \Exception("Insufficient leave balance at approval time. Available: " . ($balance ? $balance->remaining_days : 0));
                    }

                    $oldBalance = $balance->toArray();
                    $balance->used_days += $fresh->number_of_days;
                    $balance->remaining_days = $balance->allocated_days - $balance->used_days;
                    $balance->save();

                    AuditService::logModelChange(
                        'leave_balance.updated',
                        $balance,
                        $oldBalance,
                        $balance->toArray(),
                        "Deducted {$fresh->number_of_days} days for approved leave request #{$fresh->id}"
                    );
                }

                $oldReq = $fresh->toArray();
                $fresh->update([
                    'status' => 'Approved',
                    'reviewed_by' => $user->id,
                    'hr_remarks' => $request->input('hr_remarks'),
                ]);

                $freshReq = $fresh->fresh(['employee', 'leaveType', 'reviewer']);

                AuditService::logModelChange(
                    'leave_request.approved',
                    $freshReq,
                    $oldReq,
                    $freshReq->toArray(),
                    "Final HR approved leave request #{$freshReq->id}"
                );

                return $freshReq;
            });

            // Post-commit notification dispatch: Only after transaction commits successfully
            if ($approvedRequest && $approvedRequest->employee?->user) {
                $approvedRequest->employee->user->notify(new \App\Notifications\LeaveRequestApprovedNotification($approvedRequest));
            }

            return response()->json([
                'message' => 'Leave request final approved successfully and balance updated.',
                'leave_request' => $approvedRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject a leave request (Pending or Manager Approved -> Rejected).
     */
    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        // Employee cannot reject their own request
        if ($employeeId && $employeeId === $leaveRequest->employee_id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $isManager = $employeeId && $leaveRequest->employee->manager_id === $employeeId;

        if (!$isHR && !$isManager) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if (!in_array($leaveRequest->status, ['Pending', 'Manager Approved'])) {
            return response()->json(['message' => "Cannot reject request with status {$leaveRequest->status}."], 422);
        }

        $oldValues = $leaveRequest->toArray();
        $leaveRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => $user->id,
            'hr_remarks' => $request->input('remarks', $request->input('hr_remarks')),
            'manager_remarks' => $request->input('manager_remarks', $leaveRequest->manager_remarks),
        ]);

        $fresh = $leaveRequest->fresh(['employee', 'leaveType']);

        AuditService::logModelChange('leave_request.rejected', $fresh, $oldValues, $fresh->toArray(), "Rejected leave request #{$fresh->id}");

        // Dispatch rejection notification to employee
        $reason = $request->input('rejection_reason', $request->input('remarks', $request->input('manager_remarks', $request->input('hr_remarks'))));
        $role = $isHR ? 'HR' : 'Manager';
        if ($fresh->employee?->user) {
            $fresh->employee->user->notify(new \App\Notifications\LeaveRequestRejectedNotification($fresh, $reason, $role));
        }

        return response()->json([
            'message' => 'Leave request rejected successfully.',
            'leave_request' => $fresh,
        ]);
    }

    /**
     * Cancel a leave request by employee (Pending or Manager Approved -> Cancelled).
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        if ($employeeId !== $leaveRequest->employee_id && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if (!in_array($leaveRequest->status, ['Pending', 'Manager Approved'])) {
            return response()->json(['message' => "Cannot cancel leave request with status {$leaveRequest->status}."], 422);
        }

        $oldValues = $leaveRequest->toArray();
        $leaveRequest->update(['status' => 'Cancelled']);

        $fresh = $leaveRequest->fresh(['employee', 'leaveType']);

        AuditService::logModelChange('leave_request.cancelled', $fresh, $oldValues, $fresh->toArray(), "Cancelled leave request #{$fresh->id}");

        return response()->json([
            'message' => 'Leave request cancelled successfully.',
            'leave_request' => $fresh,
        ]);
    }
}
