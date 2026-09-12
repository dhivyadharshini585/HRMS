<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Payslip;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $payslipNumber = 'PS-' . strtoupper(Str::random(8));

        // Load employee, department, designation for PDF
        $payroll->loadMissing(['employee.department', 'employee.designation']);
        $employee = $payroll->employee;

        // Extract earnings and deductions components from active structure
        $structure = $employee ? $employee->salaryStructures()
            ->where('status', 'Active')
            ->with('components')
            ->first() : null;

        $components = $structure ? $structure->components : collect();
        $earnings = $components->where('type', 'Earning')->values()->toArray();
        $deductions = $components->where('type', 'Deduction')->values()->toArray();

        if (empty($earnings) && $payroll->gross_earnings > 0) {
            $earnings[] = ['name' => 'Basic Salary', 'amount' => $payroll->basic_salary];
            if ($payroll->gross_earnings > $payroll->basic_salary) {
                $earnings[] = ['name' => 'Other Allowances', 'amount' => $payroll->gross_earnings - $payroll->basic_salary];
            }
        }
        if (empty($deductions) && $payroll->total_deductions > 0) {
            $deductions[] = ['name' => 'Total Deductions', 'amount' => $payroll->total_deductions];
        }

        // Temporary payslip object for rendering view
        $tempPayslip = new Payslip([
            'payslip_number' => $payslipNumber,
            'generated_at' => now(),
        ]);

        // Render PDF via Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $html = view('payslips.pdf', [
            'payslip' => $tempPayslip,
            'payroll' => $payroll,
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions,
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        // Save into private storage
        $filename = "{$payslipNumber}.pdf";
        $storageRelativePath = "private/payslips/{$filename}";
        Storage::disk('local')->put($storageRelativePath, $pdfContent);

        // Create Payslip record with stored pdf_path
        $payslip = Payslip::create([
            'payroll_id' => $payroll->id,
            'payslip_number' => $payslipNumber,
            'generated_at' => now(),
            'pdf_path' => $storageRelativePath,
        ]);

        return response()->json($payslip, 201);
    }

    public function show(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        $payroll = $payslip->payroll;

        if (!$user->hasPermissionTo('payroll.manage') && (!$payroll->employee || $payroll->employee->user_id !== $user->id)) {
            abort(403, 'Unauthorized access to payslip.');
        }

        return response()->json($payslip->load(['payroll', 'payroll.employee']));
    }

    public function download(Request $request, Payslip $payslip)
    {
        $user = $request->user();
        $payroll = $payslip->payroll;

        $canManage = $user->hasPermissionTo('payroll.manage');
        $isOwner = $payroll && $payroll->employee && $payroll->employee->user_id === $user->id;

        if (!$canManage && !$isOwner) {
            abort(403, 'Unauthorized access to payslip.');
        }

        if (!$payroll || $payroll->status !== 'Approved') {
            return response()->json(['message' => 'Payslip does not belong to an eligible payroll.'], 422);
        }

        if (empty($payslip->pdf_path) || !Storage::disk('local')->exists($payslip->pdf_path)) {
            return response()->json(['message' => 'Payslip PDF file not found.'], 404);
        }

        $absolutePath = Storage::disk('local')->path($payslip->pdf_path);

        if (!file_exists($absolutePath)) {
            return response()->json(['message' => 'Payslip PDF file not found.'], 404);
        }

        $filename = "{$payslip->payslip_number}.pdf";

        return response()->download($absolutePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}

