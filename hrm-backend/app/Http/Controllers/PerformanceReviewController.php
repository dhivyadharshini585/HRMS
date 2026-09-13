<?php

namespace App\Http\Controllers;

use App\Models\PerformanceReview;
use App\Models\Employee;
use Illuminate\Http\Request;

class PerformanceReviewController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        $query = PerformanceReview::with(['employee', 'reviewer', 'cycle']);

        if (!$user->can('performance.manage')) {
            if ($employeeId) {
                $query->where(function($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId)
                      ->orWhere('reviewer_id', $employeeId)
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

        $reviews = $query->orderBy('created_at', 'desc')->get();
        return response()->json($reviews);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'cycle_id' => 'required|exists:performance_cycles,id',
            'rating_technical_skills' => 'required|integer|min:1|max:5',
            'rating_communication' => 'required|integer|min:1|max:5',
            'rating_teamwork' => 'required|integer|min:1|max:5',
            'rating_leadership' => 'required|integer|min:1|max:5',
            'rating_productivity' => 'required|integer|min:1|max:5',
            'rating_problem_solving' => 'required|integer|min:1|max:5',
            'rating_attendance' => 'required|integer|min:1|max:5',
            'rating_goal_achievement' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string',
            'status' => 'nullable|string|in:draft,submitted,acknowledged',
        ]);

        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage')) {
            $targetEmployee = Employee::find($validated['employee_id']);
            if (!$targetEmployee || $targetEmployee->manager_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized to create review for this employee'], 403);
            }
        }

        $validated['reviewer_id'] = $employeeId;

        $review = PerformanceReview::create($validated);

        return response()->json([
            'message' => 'Performance review created successfully',
            'data' => $review
        ], 201);
    }

    public function show(Request $request, PerformanceReview $performance_review)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage')) {
            if ($performance_review->employee_id != $employeeId && $performance_review->reviewer_id != $employeeId) {
                $targetEmployee = Employee::find($performance_review->employee_id);
                if (!$targetEmployee || $targetEmployee->manager_id != $employeeId) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            }
        }

        $performance_review->load(['employee', 'reviewer', 'cycle']);
        return response()->json($performance_review);
    }

    public function update(Request $request, PerformanceReview $performance_review)
    {
        $validated = $request->validate([
            'rating_technical_skills' => 'sometimes|required|integer|min:1|max:5',
            'rating_communication' => 'sometimes|required|integer|min:1|max:5',
            'rating_teamwork' => 'sometimes|required|integer|min:1|max:5',
            'rating_leadership' => 'sometimes|required|integer|min:1|max:5',
            'rating_productivity' => 'sometimes|required|integer|min:1|max:5',
            'rating_problem_solving' => 'sometimes|required|integer|min:1|max:5',
            'rating_attendance' => 'sometimes|required|integer|min:1|max:5',
            'rating_goal_achievement' => 'sometimes|required|integer|min:1|max:5',
            'comments' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|in:draft,submitted,acknowledged',
        ]);

        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage')) {
            if ($performance_review->reviewer_id != $employeeId) {
                // If it's the employee acknowledging it, they can only update status to acknowledged
                if ($performance_review->employee_id == $employeeId) {
                    if (isset($validated['status']) && $validated['status'] == 'acknowledged' && count($validated) == 1) {
                        // Allow
                    } else {
                        return response()->json(['message' => 'Employees can only acknowledge reviews'], 403);
                    }
                } else {
                    return response()->json(['message' => 'Unauthorized to update this review'], 403);
                }
            }
        }

        $performance_review->update($validated);

        return response()->json([
            'message' => 'Performance review updated successfully',
            'data' => $performance_review
        ]);
    }

    public function destroy(Request $request, PerformanceReview $performance_review)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('performance.manage') && $performance_review->reviewer_id != $employeeId) {
            return response()->json(['message' => 'Unauthorized to delete this review'], 403);
        }

        $performance_review->delete();

        return response()->json([
            'message' => 'Performance review deleted successfully'
        ]);
    }
}
