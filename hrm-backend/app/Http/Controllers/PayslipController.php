<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayslipController extends Controller
{
    public function generate(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        
        if (!$user->hasPermissionTo('payroll.manage')) {
            abort(403, 'Unauthorized to generate payslips.');
        }

        if ($payroll->status !== 'Approved') {
            return response()->json(['message' => 'Cannot generate payslip for unapproved payroll.'], 422);
        }

        if ($payroll->payslip) {
            return response()->json(['message' => 'Payslip already generated.'], 422);
        }

        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => 'PS-' . strtoupper(Str::random(8)),
            'generated_at' => now(),
        ]);

        return response()->json($payslip, 201);
    }

    public function show(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        $payroll = $payslip->payroll;

        if (!$user->hasPermissionTo('payroll.manage') && $payroll->employee->user_id !== $user->id) {
            abort(403, 'Unauthorized access to payslip.');
        }

        return response()->json($payslip->load(['payroll', 'payroll.employee']));
    }
}
