<?php

namespace App\Http\Controllers;

use App\Models\StatutoryPayrollRule;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatutoryRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Only Super Admin and Finance Admin can manage these rules
        if (!$request->user()->hasRole(['Super Admin', 'Finance/Payroll Admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rules = StatutoryPayrollRule::orderBy('rule_name')->get();

        return response()->json($rules);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole(['Super Admin', 'Finance/Payroll Admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rule = StatutoryPayrollRule::findOrFail($id);

        return response()->json($rule);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole(['Super Admin', 'Finance/Payroll Admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'rule_type' => 'required|in:Percentage,Fixed,Slab',
            'base_component' => 'nullable|string|max:255',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'slabs' => 'nullable|array',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);

        $rule = StatutoryPayrollRule::create($validated);

        AuditService::logModelChange('Statutory Payroll Rule Created', $rule, [], $rule->toArray(), "Created statutory payroll rule {$rule->rule_name}");

        return response()->json([
            'message' => 'Rule created successfully',
            'rule' => $rule,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole(['Super Admin', 'Finance/Payroll Admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rule = StatutoryPayrollRule::findOrFail($id);

        $validated = $request->validate([
            'rule_name' => 'sometimes|string|max:255',
            'rule_type' => 'sometimes|in:Percentage,Fixed,Slab',
            'base_component' => 'nullable|string|max:255',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'slabs' => 'nullable|array',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);

        $oldValues = $rule->toArray();
        $rule->update($validated);

        AuditService::logModelChange('Statutory Payroll Rule Updated', $rule, $oldValues, $rule->toArray(), "Updated statutory payroll rule {$rule->rule_name}");

        return response()->json([
            'message' => 'Rule updated successfully',
            'rule' => $rule,
        ]);
    }

    public function activate(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole(['Super Admin', 'Finance/Payroll Admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rule = StatutoryPayrollRule::findOrFail($id);
        $oldValues = $rule->toArray();
        $rule->update(['is_active' => true]);

        AuditService::logModelChange('Statutory Payroll Rule Activated', $rule, $oldValues, $rule->toArray(), "Activated statutory payroll rule {$rule->rule_name}");

        return response()->json([
            'message' => 'Rule activated successfully',
            'rule' => $rule,
        ]);
    }

    public function deactivate(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole(['Super Admin', 'Finance/Payroll Admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rule = StatutoryPayrollRule::findOrFail($id);
        $oldValues = $rule->toArray();
        $rule->update(['is_active' => false]);

        AuditService::logModelChange('Statutory Payroll Rule Deactivated', $rule, $oldValues, $rule->toArray(), "Deactivated statutory payroll rule {$rule->rule_name}");

        return response()->json([
            'message' => 'Rule deactivated successfully',
            'rule' => $rule,
        ]);
    }
}
