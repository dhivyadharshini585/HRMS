<?php

namespace App\Http\Controllers;

use App\Models\Training;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        $query = Training::with(['trainer']);
        
        $trainings = $query->orderBy('start_date', 'desc')->get();
        return response()->json($trainings);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'training_name' => 'required|string|max:255',
            'trainer_employee_id' => 'nullable|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|string|in:scheduled,ongoing,completed,cancelled',
        ]);

        $training = Training::create($validated);

        return response()->json([
            'message' => 'Training created successfully',
            'data' => $training
        ], 201);
    }

    public function show(Training $training)
    {
        $training->load(['trainer', 'attendees.employee']);
        return response()->json($training);
    }

    public function update(Request $request, Training $training)
    {
        $validated = $request->validate([
            'training_name' => 'sometimes|required|string|max:255',
            'trainer_employee_id' => 'sometimes|nullable|exists:employees,id',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'status' => 'sometimes|required|string|in:scheduled,ongoing,completed,cancelled',
        ]);

        $training->update($validated);

        return response()->json([
            'message' => 'Training updated successfully',
            'data' => $training
        ]);
    }

    public function destroy(Training $training)
    {
        $training->delete();
        return response()->json([
            'message' => 'Training deleted successfully'
        ]);
    }
}
