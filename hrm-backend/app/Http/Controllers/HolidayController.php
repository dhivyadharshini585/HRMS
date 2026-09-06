<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\AuditService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * Display a listing of holidays.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('holidays.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = Holiday::query();

        if ($request->filled('year')) {
            $query->whereYear('holiday_date', $request->input('year'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $holidays = $query->orderBy('holiday_date')->get();

        return response()->json(['data' => $holidays]);
    }

    /**
     * Display the specified holiday.
     */
    public function show(Request $request, Holiday $holiday)
    {
        $user = $request->user();

        if (!$user->can('holidays.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        return response()->json($holiday);
    }

    /**
     * Store a newly created holiday.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->can('holidays.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date|unique:holidays,holiday_date',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'holiday_type' => 'required|in:National,Company,Regional,Optional',
        ]);

        $holiday = Holiday::create($validated);

        AuditService::logModelChange('holiday.created', $holiday, [], $holiday->toArray(), "Created holiday {$holiday->name}");

        return response()->json($holiday, 201);
    }

    /**
     * Update the specified holiday.
     */
    public function update(Request $request, Holiday $holiday)
    {
        $user = $request->user();

        if (!$user->can('holidays.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date|unique:holidays,holiday_date,' . $holiday->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'holiday_type' => 'required|in:National,Company,Regional,Optional',
        ]);

        $oldValues = $holiday->toArray();
        $holiday->update($validated);
        $fresh = $holiday->fresh();

        AuditService::logModelChange('holiday.updated', $fresh, $oldValues, $fresh->toArray(), "Updated holiday {$holiday->name}");

        return response()->json($fresh);
    }

    /**
     * Remove the specified holiday.
     */
    public function destroy(Request $request, Holiday $holiday)
    {
        $user = $request->user();

        if (!$user->can('holidays.manage')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $oldValues = $holiday->toArray();
        $holiday->delete();

        AuditService::logModelChange('holiday.deleted', $holiday, $oldValues, [], "Deleted holiday {$holiday->name}");

        return response()->json(['message' => 'Holiday deleted successfully.']);
    }
}
