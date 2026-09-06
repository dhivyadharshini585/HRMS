<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignationRequest;
use App\Http\Requests\UpdateDesignationRequest;
use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Designation::withCount('employees');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesignationRequest $request)
    {
        $designation = Designation::create($request->validated());

        return response()->json([
            'message' => 'Designation created successfully',
            'data' => $designation
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $designation = Designation::withCount('employees')->findOrFail($id);
        return response()->json($designation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDesignationRequest $request, string $id)
    {
        $designation = Designation::findOrFail($id);
        $designation->update($request->validated());

        return response()->json([
            'message' => 'Designation updated successfully',
            'data' => $designation
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $designation = Designation::withCount('employees')->findOrFail($id);

        if ($designation->employees_count > 0) {
            return response()->json([
                'message' => 'Cannot delete designation. There are employees assigned to this designation.'
            ], 422);
        }

        $designation->delete();

        return response()->json([
            'message' => 'Designation deleted successfully'
        ]);
    }
}
