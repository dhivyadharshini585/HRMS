<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Services\AuditService;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of leave types.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('leave_types.view') && !$user->can('leave.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = LeaveType::query();

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $leaveTypes = $query->orderBy('name')->get();

        return response()->json(['data' => $leaveTypes]);
    }

    /**
     * Display the specified leave type.
     */
    public function show(Request $request, LeaveType $leaveType)
    {
        $user = $request->user();

        if (!$user->can('leave_types.view') && !$user->can('leave.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        return response()->json($leaveType);
    }

    /**
     * Store a newly created leave type.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->can('leave_types.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:leave_types,name|max:255',
            'description' => 'nullable|string',
            'default_annual_allocation' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_unpaid' => 'boolean',
        ]);

        $leaveType = LeaveType::create($validated);

        AuditService::logModelChange('leave_type.created', $leaveType, [], $leaveType->toArray(), "Created leave type {$leaveType->name}");

        return response()->json($leaveType, 201);
    }

    /**
     * Update the specified leave type.
     */
    public function update(Request $request, LeaveType $leaveType)
    {
        $user = $request->user();

        if (!$user->can('leave_types.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:leave_types,name,' . $leaveType->id,
            'description' => 'nullable|string',
            'default_annual_allocation' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_unpaid' => 'boolean',
        ]);

        $oldValues = $leaveType->toArray();
        $leaveType->update($validated);
        $fresh = $leaveType->fresh();

        AuditService::logModelChange('leave_type.updated', $fresh, $oldValues, $fresh->toArray(), "Updated leave type {$leaveType->name}");

        return response()->json($fresh);
    }

    /**
     * Remove the specified leave type.
     */
    public function destroy(Request $request, LeaveType $leaveType)
    {
        $user = $request->user();

        if (!$user->can('leave_types.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $oldValues = $leaveType->toArray();
        $leaveType->delete();

        AuditService::logModelChange('leave_type.deleted', $leaveType, $oldValues, [], "Deleted leave type {$leaveType->name}");

        return response()->json(['message' => 'Leave type deleted successfully.']);
    }
}
