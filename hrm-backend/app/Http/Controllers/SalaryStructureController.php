<?php

namespace App\Http\Controllers;

use App\Models\SalaryStructure;
use App\Models\Employee;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index(Request $request)
    {
        $structures = SalaryStructure::with(['employee', 'components'])->get();
        return response()->json($structures);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'status' => 'required|in:Active,Inactive',
            'components' => 'required|array',
            'components.*.name' => 'required|string',
            'components.*.type' => 'required|in:Earning,Deduction',
            'components.*.amount' => 'required|numeric|min:0',
        ]);

        $structure = SalaryStructure::create([
            'employee_id' => $validated['employee_id'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'status' => $validated['status'],
        ]);

        if (isset($validated['components'])) {
            $structure->components()->createMany($validated['components']);
        }

        return response()->json($structure->load('components'), 201);
    }

    public function show(SalaryStructure $salaryStructure)
    {
        return response()->json($salaryStructure->load('components'));
    }

    public function update(Request $request, SalaryStructure $salaryStructure)
    {
        $validated = $request->validate([
            'effective_from' => 'date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'status' => 'in:Active,Inactive',
            'components' => 'array',
            'components.*.name' => 'required_with:components|string',
            'components.*.type' => 'required_with:components|in:Earning,Deduction',
            'components.*.amount' => 'required_with:components|numeric|min:0',
        ]);

        $salaryStructure->update($request->only(['effective_from', 'effective_to', 'status']));

        if (isset($validated['components'])) {
            $salaryStructure->components()->delete();
            $salaryStructure->components()->createMany($validated['components']);
        }

        return response()->json($salaryStructure->load('components'));
    }

    public function destroy(SalaryStructure $salaryStructure)
    {
        $salaryStructure->delete();
        return response()->json(null, 204);
    }
}
