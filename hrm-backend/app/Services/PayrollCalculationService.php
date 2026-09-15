<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Exception;

class PayrollCalculationService
{
    /**
     * Calculate and draft payroll for an employee for a specific month and year.
     *
     * @param Employee $employee
     * @param int $month
     * @param int $year
     * @return Payroll
     * @throws Exception
     */
    public function calculateDraft(Employee $employee, int $month, int $year): Payroll
    {
        // 1. Check if payroll already exists for this month to prevent duplicates
        $existingPayroll = Payroll::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existingPayroll) {
            throw new Exception("Payroll already exists for this employee for {$month}/{$year}");
        }

        // 2. Find active salary structure for the period
        // For simplicity, we get the structure active on the end of the month
        $periodEnd = Carbon::create($year, $month)->endOfMonth();

        $structure = $employee->salaryStructures()
            ->where('status', 'Active')
            ->where('effective_from', '<=', $periodEnd)
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $periodEnd);
            })
            ->first();

        if (!$structure) {
            throw new Exception("No active salary structure found for employee for {$month}/{$year}");
        }

        // 3. Extract components
        $components = $structure->components;
        $basicSalaryComponent = $components->filter(function ($component) {
            return $component->type === 'Earning' && stripos($component->name, 'Basic') !== false;
        })->first();
        $basicSalaryAmount = $basicSalaryComponent ? $basicSalaryComponent->amount : 0;

        $grossEarnings = $components->where('type', 'Earning')->sum('amount');

        // Find existing generic manual deductions to avoid double deducting
        $manualDeductionNames = $components->where('type', 'Deduction')
            ->pluck('name')
            ->map(fn($n) => strtolower(trim($n)))
            ->toArray();
        $standardDeductions = $components->where('type', 'Deduction')->sum('amount');

        // 4. Calculate Loss of Pay (LOP) from Unpaid Leaves
        $lopDays = $this->calculateUnpaidLeaveDays($employee, $month, $year);
        $daysInMonth = $periodEnd->daysInMonth;

        // Calculate LOP Deduction (Pro-rata based on Gross Earnings)
        $lopDeductionAmount = 0;
        if ($lopDays > 0) {
            $lopDeductionAmount = ($grossEarnings / $daysInMonth) * $lopDays;
        }

        // 5. Calculate Statutory Deductions
        $statutoryDeductionsTotal = 0;
        $appliedStatutoryRules = [];

        $activeRules = \App\Models\StatutoryPayrollRule::where('is_active', true)
            ->where('effective_from', '<=', $periodEnd)
            ->where(function ($query) use ($periodEnd) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $periodEnd);
            })
            ->get();

        foreach ($activeRules as $rule) {
            // Prevent double deduction if a generic salary component has the same name
            if (in_array(strtolower(trim($rule->rule_name)), $manualDeductionNames)) {
                continue;
            }

            $deductionAmount = 0;

            if ($rule->rule_type === 'Fixed') {
                $deductionAmount = $rule->fixed_amount ?? 0;
            } elseif ($rule->rule_type === 'Percentage') {
                $baseAmount = 0;
                if ($rule->base_component === 'Basic' || $rule->base_component === 'Basic Salary') {
                    $baseAmount = $basicSalaryAmount;
                } elseif ($rule->base_component === 'Gross' || $rule->base_component === 'Gross Salary' || $rule->base_component === 'Gross Earnings') {
                    $baseAmount = $grossEarnings;
                }
                $deductionAmount = $baseAmount * (($rule->percentage ?? 0) / 100);
            } elseif ($rule->rule_type === 'Slab' && is_array($rule->slabs)) {
                // Slabs expect format: [["min" => 0, "max" => 10000, "amount" => 0], ["min" => 10001, "max" => null, "amount" => 200]]
                $baseAmount = $grossEarnings; // Defaulting slab base to gross earnings, could be configurable
                foreach ($rule->slabs as $slab) {
                    $min = $slab['min'] ?? 0;
                    $max = $slab['max'] ?? INF;
                    if ($max === null) $max = INF;

                    if ($baseAmount >= $min && $baseAmount <= $max) {
                        $deductionAmount = $slab['amount'] ?? 0;
                        break;
                    }
                }
            }

            if ($deductionAmount > 0) {
                $statutoryDeductionsTotal += $deductionAmount;
                $appliedStatutoryRules[] = [
                    'rule' => $rule,
                    'amount' => $deductionAmount
                ];
            }
        }

        $totalDeductions = $standardDeductions + $lopDeductionAmount + $statutoryDeductionsTotal;
        $netSalary = $grossEarnings - $totalDeductions;

        // Ensure net salary is not negative
        $netSalary = max(0, $netSalary);

        // 6. Aggregate Attendance Data
        $periodStart = Carbon::create($year, $month)->startOfMonth()->format('Y-m-d');
        $periodEndStr = $periodEnd->format('Y-m-d');

        $attendances = \App\Models\Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$periodStart, $periodEndStr])
            ->get();

        $totalWorkingMinutes = (int) $attendances->sum('working_minutes');
        $totalOvertimeMinutes = (int) $attendances->sum('overtime_minutes');

        // 7. Create Draft Payroll Record
        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'month' => $month,
            'year' => $year,
            'basic_salary' => $basicSalaryAmount,
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'total_working_minutes' => $totalWorkingMinutes > 0 ? $totalWorkingMinutes : null,
            'total_overtime_minutes' => $totalOvertimeMinutes > 0 ? $totalOvertimeMinutes : null,
            'status' => 'Draft',
        ]);

        // 8. Save Payroll Components Snapshot
        foreach ($components as $component) {
            \App\Models\PayrollComponent::create([
                'payroll_id' => $payroll->id,
                'component_name' => $component->name,
                'component_type' => $component->type,
                'component_source' => 'Fixed',
                'amount' => $component->amount,
            ]);
        }

        if ($lopDeductionAmount > 0) {
            \App\Models\PayrollComponent::create([
                'payroll_id' => $payroll->id,
                'component_name' => 'Loss of Pay (LOP)',
                'component_type' => 'Deduction',
                'component_source' => 'LOP',
                'amount' => $lopDeductionAmount,
            ]);
        }

        foreach ($appliedStatutoryRules as $appliedRule) {
            \App\Models\PayrollComponent::create([
                'payroll_id' => $payroll->id,
                'component_name' => $appliedRule['rule']->rule_name,
                'component_type' => 'Deduction',
                'component_source' => 'Statutory',
                'amount' => $appliedRule['amount'],
                'statutory_payroll_rule_id' => $appliedRule['rule']->id,
            ]);
        }

        return $payroll;
    }

    /**
     * Calculate total days of approved unpaid leave in the given month/year.
     */
    private function calculateUnpaidLeaveDays(Employee $employee, int $month, int $year): float
    {
        $periodStart = Carbon::create($year, $month)->startOfMonth()->format('Y-m-d');
        $periodEnd = Carbon::create($year, $month)->endOfMonth()->format('Y-m-d');

        // Find approved leaves overlapping with this month that are unpaid
        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'Approved')
            ->whereHas('leaveType', function($q) {
                $q->where('is_unpaid', true);
            })
            ->where('from_date', '<=', $periodEnd)
            ->where('to_date', '>=', $periodStart)
            ->get();

        $lopDays = 0;

        foreach ($leaves as $leave) {
            $leaveStart = Carbon::parse($leave->from_date);
            $leaveEnd = Carbon::parse($leave->to_date);

            // Limit the leave window to the current month's boundaries
            $effectiveStart = $leaveStart->max(Carbon::create($year, $month)->startOfMonth());
            $effectiveEnd = $leaveEnd->min(Carbon::create($year, $month)->endOfMonth());

            if ($effectiveStart->lte($effectiveEnd)) {
                // Add 1 because start and end are inclusive (e.g. 1st to 1st = 1 day)
                $days = $effectiveStart->diffInDays($effectiveEnd) + 1;
                // If it's a fractional leave in original request, we simplify by prorating
                // but usually dates are full days. For simplicity we assume full days here.
                $lopDays += $days;
            }
        }

        return $lopDays;
    }
}
