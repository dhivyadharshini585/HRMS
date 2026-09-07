<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use App\Services\PayrollCalculationService;
use Illuminate\Http\Request;
use Exception;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollCalculationService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Payroll::with(['employee', 'approver', 'payslip']);
        
        // Enforce ownership: if user cannot manage payroll, they can only view their own
        if (!$user->hasPermissionTo('payroll.manage')) {
            $query->whereHas('employee', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return response()->json($query->orderBy('year', 'desc')->orderBy('month', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $payroll = $this->payrollService->calculateDraft($employee, $validated['month'], $validated['year']);
            return response()->json($payroll, 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, Payroll $payroll)
    {
        $user = $request->user();

        // Enforce ownership
        if (!$user->hasPermissionTo('payroll.manage') && $payroll->employee->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payroll record.');
        }

        return response()->json($payroll->load(['employee', 'approver', 'payslip']));
    }

    public function approve(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        
        if (!$user->hasPermissionTo('payroll.approve')) {
            abort(403, 'Unauthorized to approve payroll.');
        }

        if ($payroll->status !== 'Draft' && $payroll->status !== 'Processed') {
            return response()->json(['message' => 'Payroll cannot be approved in its current status.'], 422);
        }

        $payroll->update([
            'status' => 'Approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json($payroll);
    }
}
