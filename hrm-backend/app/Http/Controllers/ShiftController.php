<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\EmployeeShiftRotation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShiftController extends Controller
{
    /**
     * Display a listing of shifts.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('shifts.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = Shift::query();

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $shifts = $query->orderBy('name')->get();

        return response()->json(['data' => $shifts]);
    }

    /**
     * Display the specified shift.
     */
    public function show(Request $request, Shift $shift)
    {
        $user = $request->user();

        if (!$user->can('shifts.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        return response()->json($shift);
    }

    /**
     * Store a newly created shift.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->can('shifts.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:shifts,name|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'grace_period_minutes' => 'integer|min:0',
            'overtime_enabled' => 'boolean',
            'overtime_threshold_minutes' => 'integer|min:0',
        ]);

        $shift = Shift::create($validated);

        AuditService::logModelChange('shift.created', $shift, [], $shift->toArray(), "Created shift {$shift->name}");

        return response()->json($shift, 201);
    }

    /**
     * Update the specified shift.
     */
    public function update(Request $request, Shift $shift)
    {
        $user = $request->user();

        if (!$user->can('shifts.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shifts,name,' . $shift->id,
            'start_time' => 'required',
            'end_time' => 'required',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'grace_period_minutes' => 'integer|min:0',
            'overtime_enabled' => 'boolean',
            'overtime_threshold_minutes' => 'integer|min:0',
        ]);

        $oldValues = $shift->toArray();
        $shift->update($validated);
        $fresh = $shift->fresh();

        AuditService::logModelChange('shift.updated', $fresh, $oldValues, $fresh->toArray(), "Updated shift {$shift->name}");

        return response()->json($fresh);
    }

    /**
     * Remove the specified shift.
     */
    public function destroy(Request $request, Shift $shift)
    {
        $user = $request->user();

        if (!$user->can('shifts.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $oldValues = $shift->toArray();
        $shift->delete();

        AuditService::logModelChange('shift.deleted', $shift, $oldValues, [], "Deleted shift {$shift->name}");

        return response()->json(['message' => 'Shift deleted successfully.']);
    }

    /**
     * Assign a default shift to an employee.
     */
    public function assignToEmployee(Request $request, Employee $employee)
    {
        $user = $request->user();

        if (!$user->can('shifts.manage') && !$user->can('employees.update')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $request->validate([
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        $oldShiftId = $employee->shift_id;
        $employee->update(['shift_id' => $request->input('shift_id')]);
        $employee->load('shift');

        AuditService::log('employee.shift_assigned', "Assigned shift ID {$request->input('shift_id')} to employee {$employee->id}", [
            'entity_type' => Employee::class,
            'entity_id' => $employee->id,
            'old_values' => ['shift_id' => $oldShiftId],
            'new_values' => ['shift_id' => $employee->shift_id],
        ]);

        return response()->json([
            'message' => 'Shift assigned successfully.',
            'employee' => $employee,
        ]);
    }

    /**
     * List shift rotations for an employee.
     */
    public function listRotations(Request $request, Employee $employee)
    {
        $user = $request->user();

        if (!$user->can('shifts.view') && $user->employee?->id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $rotations = $employee->shiftRotations()->with('shift')->orderBy('start_date', 'desc')->get();

        return response()->json(['data' => $rotations]);
    }

    /**
     * Create a shift rotation for an employee.
     */
    public function createRotation(Request $request, Employee $employee)
    {
        $user = $request->user();

        if (!$user->can('shifts.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Check for overlap
        $overlap = EmployeeShiftRotation::where('employee_id', $employee->id)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                      ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                      ->orWhere(function ($q) use ($validated) {
                          $q->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                      });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'The given date range overlaps with an existing shift rotation for this employee.',
                'errors' => ['date_range' => ['Overlapping shift rotation exists.']]
            ], 422);
        }

        $rotation = $employee->shiftRotations()->create([
            'shift_id' => $validated['shift_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'Scheduled',
        ]);
        
        $rotation->load('shift');

        AuditService::log('shift.rotation_created', "Assigned shift rotation to employee {$employee->id}", [
            'entity_type' => EmployeeShiftRotation::class,
            'entity_id' => $rotation->id,
            'new_values' => $rotation->toArray(),
        ]);

        return response()->json($rotation, 201);
    }

    /**
     * Delete a shift rotation.
     */
    public function deleteRotation(Request $request, EmployeeShiftRotation $rotation)
    {
        $user = $request->user();

        if (!$user->can('shifts.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $oldValues = $rotation->toArray();
        $rotation->delete();

        AuditService::log('shift.rotation_deleted', "Deleted shift rotation {$rotation->id}", [
            'entity_type' => EmployeeShiftRotation::class,
            'entity_id' => $rotation->id,
            'old_values' => $oldValues,
        ]);

        return response()->json(['message' => 'Rotation deleted successfully.']);
    }
}
