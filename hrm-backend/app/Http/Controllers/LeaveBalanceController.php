<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveBalanceController extends Controller
{
    /**
     * Display leave balances for an employee.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // If employee_id is specified in request
        if ($request->filled('employee_id')) {
            $employeeId = $request->input('employee_id');
            $targetEmployee = Employee::find($employeeId);

            if (!$targetEmployee) {
                return response()->json(['message' => 'Employee not found.'], 404);
            }

            // Check RBAC: User must have leave.view/employees.view permission OR be manager/owner
            $isOwner = $user->employee?->id === (int) $employeeId;
            $isManager = $targetEmployee->manager_id === $user->employee?->id;
            $canView = $user->can('leave.view') || $user->can('employees.view') || $isOwner || $isManager;

            if (!$canView) {
                return response()->json(['message' => 'Unauthorized action.'], 403);
            }

            $employee = $targetEmployee;
        } else {
            // Default to authenticated employee
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'No linked employee profile found for user.'], 400);
            }
        }

        $year = $request->input('year', 2026);

        // Ensure balances exist for all active leave types for this year
        $activeTypes = LeaveType::where('is_active', true)->get();
        foreach ($activeTypes as $type) {
            LeaveBalance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                ],
                [
                    'allocated_days' => $type->default_annual_allocation,
                    'used_days' => 0,
                    'remaining_days' => $type->default_annual_allocation,
                ]
            );
        }

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get();

        return response()->json(['data' => $balances]);
    }

    /**
     * Adjust an employee's leave balance (HR action).
     */
    public function adjust(Request $request, Employee $employee)
    {
        $user = $request->user();

        // RBAC: Must be HR Admin, Super Admin, or have leave_types.manage permission
        if (!$user->can('leave_types.manage') && !$user->hasRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'allocated_days' => 'required|numeric|min:0',
            'used_days' => 'nullable|numeric|min:0',
            'year' => 'nullable|integer|min:2020|max:2035',
        ]);

        $year = $validated['year'] ?? 2026;
        $allocated = (float) $validated['allocated_days'];
        $used = isset($validated['used_days']) ? (float) $validated['used_days'] : null;

        $adjustedBalance = DB::transaction(function () use ($employee, $validated, $year, $allocated, $used) {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = LeaveBalance::create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $validated['leave_type_id'],
                    'allocated_days' => $allocated,
                    'used_days' => $used ?? 0,
                    'remaining_days' => $allocated - ($used ?? 0),
                    'year' => $year,
                ]);
                $oldValues = [];
            } else {
                $oldValues = $balance->toArray();
                $currentUsed = $used !== null ? $used : $balance->used_days;
                $balance->allocated_days = $allocated;
                $balance->used_days = $currentUsed;
                $balance->remaining_days = $allocated - $currentUsed;
                $balance->save();
            }

            $fresh = $balance->fresh(['leaveType', 'employee']);

            AuditService::logModelChange(
                'leave_balance.updated',
                $fresh,
                $oldValues,
                $fresh->toArray(),
                "Adjusted leave balance for employee {$employee->id}, leave type {$validated['leave_type_id']}"
            );

            return $fresh;
        });

        return response()->json([
            'message' => 'Leave balance adjusted successfully.',
            'balance' => $adjustedBalance,
        ]);
    }
}
