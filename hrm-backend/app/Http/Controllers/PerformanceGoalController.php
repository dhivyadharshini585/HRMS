<?php

namespace App\Http\Controllers;

use App\Models\PerformanceGoal;
use App\Models\Employee;
use Illuminate\Http\Request;

class PerformanceGoalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        $query = PerformanceGoal::with(['employee', 'cycle']);

        if (!$user->can('performance.manage')) {
            if ($employeeId) {
                $query->where(function($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId)
                      ->orWhereHas('employee', function($q2) use ($employeeId) {
                          $q2->where('manager_id', $employeeId);
                      });
                });
            } else {
                $query->where('id', '<', 0);
            }
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('cycle_id')) {
            $query->where('cycle_id', $request->cycle_id);
        }

        $goals = $query->orderBy('deadline', 'asc')->get();
        return response()->json($goals);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'cycle_id' => 'required|exists:performance_cycles,id',
            'goal' => 'required|string',
            'target' => 'required|string',
            'deadline' => 'required|date',
            'progress' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
        ]);

        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage') && $validated['employee_id'] != $employeeId) {
            $targetEmployee = Employee::find($validated['employee_id']);
            if (!$targetEmployee || $targetEmployee->manager_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized to create goal for this employee'], 403);
            }
        }

        $goal = PerformanceGoal::create($validated);

        return response()->json([
            'message' => 'Performance goal created successfully',
            'data' => $goal
        ], 201);
    }

    public function show(Request $request, PerformanceGoal $performance_goal)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage') && $performance_goal->employee_id != $employeeId) {
            $targetEmployee = Employee::find($performance_goal->employee_id);
            if (!$targetEmployee || $targetEmployee->manager_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $performance_goal->load(['employee', 'cycle']);
        return response()->json($performance_goal);
    }

    public function update(Request $request, PerformanceGoal $performance_goal)
    {
        $validated = $request->validate([
            'goal' => 'sometimes|required|string',
            'target' => 'sometimes|required|string',
            'deadline' => 'sometimes|required|date',
            'progress' => 'sometimes|nullable|integer|min:0|max:100',
            'status' => 'sometimes|nullable|string|in:pending,in_progress,completed,cancelled',
        ]);

        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage') && $performance_goal->employee_id != $employeeId) {
            $targetEmployee = Employee::find($performance_goal->employee_id);
            if (!$targetEmployee || $targetEmployee->manager_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized to update this goal'], 403);
            }
        }

        $performance_goal->update($validated);

        return response()->json([
            'message' => 'Performance goal updated successfully',
            'data' => $performance_goal
        ]);
    }

    public function destroy(Request $request, PerformanceGoal $performance_goal)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage') && $performance_goal->employee_id != $employeeId) {
            $targetEmployee = Employee::find($performance_goal->employee_id);
            if (!$targetEmployee || $targetEmployee->manager_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized to delete this goal'], 403);
            }
        }

        $performance_goal->delete();

        return response()->json([
            'message' => 'Performance goal deleted successfully'
        ]);
    }
}
