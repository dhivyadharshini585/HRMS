<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollReportController extends Controller
{
    /**
     * Get Monthly Payroll Summary
     */
    public function monthlySummary(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $month = $request->month;
        $year = $request->year;

        $payrolls = Payroll::where('month', $month)->where('year', $year)->get();

        $summary = [
            'month' => $month,
            'year' => $year,
            'total_employees_processed' => $payrolls->count(),
            'total_gross_salary' => $payrolls->sum('gross_earnings'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'total_net_salary' => $payrolls->sum('net_salary'),
            'pending_payroll_count' => $payrolls->where('status', 'Draft')->count(),
            'approved_payroll_count' => $payrolls->where('status', 'Approved')->count(),
        ];

        return response()->json($summary);
    }

    /**
     * Get Department-wise Payroll Report
     */
    public function departmentWise(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $month = $request->month;
        $year = $request->year;

        $departments = Department::all();
        $report = [];

        foreach ($departments as $department) {
            $payrolls = Payroll::whereHas('employee', function ($query) use ($department) {
                $query->where('department_id', $department->id);
            })->where('month', $month)->where('year', $year)->get();

            if ($payrolls->count() > 0) {
                $report[] = [
                    'department' => $department->name,
                    'employee_count' => $payrolls->count(),
                    'gross_salary_total' => $payrolls->sum('gross_earnings'),
                    'deductions_total' => $payrolls->sum('total_deductions'),
                    'net_salary_total' => $payrolls->sum('net_salary'),
                ];
            }
        }

        return response()->json($report);
    }

    public function deductionReport(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $month = $request->month;
        $year = $request->year;

        $payrolls = Payroll::where('month', $month)->where('year', $year)->get();

        $report = [
            'PF_total' => 0,
            'ESI_total' => 0,
            'Professional_Tax_total' => 0,
            'TDS_total' => 0,
            'other_deductions_total' => 0,
            'LOP_total' => 0,
        ];

        foreach ($payrolls as $payroll) {
            $breakdown = is_string($payroll->component_breakdown) ? json_decode($payroll->component_breakdown, true) : $payroll->component_breakdown;
            if (is_array($breakdown)) {
                foreach ($breakdown as $comp) {
                    if (isset($comp['type']) && $comp['type'] === 'Deduction') {
                        $name = strtoupper($comp['name']);
                        $amount = (float)$comp['amount'];

                        if (str_contains($name, 'PF') || str_contains($name, 'PROVIDENT')) {
                            $report['PF_total'] += $amount;
                        } elseif (str_contains($name, 'ESI')) {
                            $report['ESI_total'] += $amount;
                        } elseif (str_contains($name, 'PROFESSIONAL TAX') || str_contains($name, 'PT')) {
                            $report['Professional_Tax_total'] += $amount;
                        } elseif (str_contains($name, 'TDS') || str_contains($name, 'TAX')) {
                            $report['TDS_total'] += $amount;
                        } elseif (str_contains($name, 'LOSS OF PAY') || str_contains($name, 'LOP')) {
                            $report['LOP_total'] += $amount;
                        } else {
                            $report['other_deductions_total'] += $amount;
                        }
                    }
                }
            }
        }

        return response()->json($report);
    }

    /**
     * Get Employee Payroll Report
     */
    public function employeeReport(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'nullable|in:Draft,Approved',
        ]);

        $query = Payroll::with(['employee.department'])->where('month', $request->month)->where('year', $request->year);

        if ($request->has('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payrolls = $query->get();

        $report = $payrolls->map(function ($payroll) {
            return [
                'employee' => $payroll->employee->first_name . ' ' . $payroll->employee->last_name,
                'employee_id' => $payroll->employee->employee_id,
                'department' => $payroll->employee->department ? $payroll->employee->department->name : 'N/A',
                'payroll_period' => $payroll->month . '/' . $payroll->year,
                'gross' => $payroll->gross_earnings,
                'deductions' => $payroll->total_deductions,
                'net_salary' => $payroll->net_salary,
                'status' => $payroll->status,
            ];
        });

        return response()->json($report);
    }
}
