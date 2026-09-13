<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PerformanceGoal;
use App\Models\PerformanceCycle;
use App\Models\Employee;

class PerformanceGoalSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = PerformanceCycle::where('name', '2026 Annual Performance Cycle')->first();
        if (!$cycle) return;

        $emp5 = Employee::where('employee_code', 'EMP-005')->first(); // Software Engineer
        if ($emp5) {
            PerformanceGoal::firstOrCreate(
                ['employee_id' => $emp5->id, 'cycle_id' => $cycle->id, 'goal' => 'Complete React Certification'],
                ['target' => 'Obtain certification by end of Q2', 'deadline' => '2026-06-30', 'progress' => 50, 'status' => 'in_progress']
            );
            PerformanceGoal::firstOrCreate(
                ['employee_id' => $emp5->id, 'cycle_id' => $cycle->id, 'goal' => 'Reduce API Latency'],
                ['target' => 'Optimize queries to reduce latency by 20%', 'deadline' => '2026-09-30', 'progress' => 25, 'status' => 'in_progress']
            );
            PerformanceGoal::firstOrCreate(
                ['employee_id' => $emp5->id, 'cycle_id' => $cycle->id, 'goal' => 'Write Unit Tests'],
                ['target' => 'Achieve 80% test coverage', 'deadline' => '2026-12-31', 'progress' => 0, 'status' => 'pending']
            );
        }

        $emp3 = Employee::where('employee_code', 'EMP-003')->first(); // HR Exec
        if ($emp3) {
            PerformanceGoal::firstOrCreate(
                ['employee_id' => $emp3->id, 'cycle_id' => $cycle->id, 'goal' => 'Improve Recruitment Time'],
                ['target' => 'Reduce time to hire to 20 days', 'deadline' => '2026-11-30', 'progress' => 75, 'status' => 'in_progress']
            );
            PerformanceGoal::firstOrCreate(
                ['employee_id' => $emp3->id, 'cycle_id' => $cycle->id, 'goal' => 'Onboard New Hires'],
                ['target' => 'Successfully onboard 5 new engineers', 'deadline' => '2026-05-31', 'progress' => 100, 'status' => 'completed']
            );
        }
    }
}
