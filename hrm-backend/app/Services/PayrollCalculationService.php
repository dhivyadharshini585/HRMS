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
        $standardDeductions = $components->where('type', 'Deduction')->sum('amount');

        // 4. Calculate Loss of Pay (LOP) from Unpaid Leaves
        $lopDays = $this->calculateUnpaidLeaveDays($employee, $month, $year);
        $daysInMonth = $periodEnd->daysInMonth;
        
        // Calculate LOP Deduction (Pro-rata based on Gross Earnings)
        $lopDeductionAmount = 0;
        if ($lopDays > 0) {
            $lopDeductionAmount = ($grossEarnings / $daysInMonth) * $lopDays;
        }

        $totalDeductions = $standardDeductions + $lopDeductionAmount;
        $netSalary = $grossEarnings - $totalDeductions;

        // Ensure net salary is not negative
        $netSalary = max(0, $netSalary);

        // 5. Create Draft Payroll Record
        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'month' => $month,
            'year' => $year,
            'basic_salary' => $basicSalaryAmount,
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'status' => 'Draft',
        ]);

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
