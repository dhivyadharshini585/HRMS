<?php

namespace App\Http\Controllers;

use App\Models\PerformanceCycle;
use Illuminate\Http\Request;

class PerformanceCycleController extends Controller
{
    public function index()
    {
        $cycles = PerformanceCycle::orderBy('start_date', 'desc')->get();
        return response()->json($cycles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|string|in:active,completed,planned',
        ]);

        $cycle = PerformanceCycle::create($validated);

        return response()->json([
            'message' => 'Performance cycle created successfully',
            'data' => $cycle
        ], 201);
    }

    public function show(PerformanceCycle $performance_cycle)
    {
        return response()->json($performance_cycle);
    }

    public function update(Request $request, PerformanceCycle $performance_cycle)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'status' => 'sometimes|required|string|in:active,completed,planned',
        ]);

        $performance_cycle->update($validated);

        return response()->json([
            'message' => 'Performance cycle updated successfully',
            'data' => $performance_cycle
        ]);
    }

    public function destroy(PerformanceCycle $performance_cycle)
    {
        $performance_cycle->delete();
        return response()->json([
            'message' => 'Performance cycle deleted successfully'
        ]);
    }
}
