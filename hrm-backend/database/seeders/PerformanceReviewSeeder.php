<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PerformanceReview;
use App\Models\PerformanceCycle;
use App\Models\Employee;

class PerformanceReviewSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = PerformanceCycle::where('name', '2026 Annual Performance Cycle')->first();
        if (!$cycle) return;

        $emp5 = Employee::where('employee_code', 'EMP-005')->first();
        $manager = Employee::where('employee_code', 'EMP-004')->first();

        if ($emp5 && $manager) {
            PerformanceReview::firstOrCreate(
                ['employee_id' => $emp5->id, 'reviewer_id' => $manager->id, 'cycle_id' => $cycle->id],
                [
                    'rating_technical_skills' => 4,
                    'rating_communication' => 3,
                    'rating_teamwork' => 5,
                    'rating_leadership' => 3,
                    'rating_productivity' => 4,
                    'rating_problem_solving' => 4,
                    'rating_attendance' => 5,
                    'rating_goal_achievement' => 4,
                    'comments' => 'Great performance this year. Need to improve on communication during standups.',
                    'status' => 'acknowledged'
                ]
            );
        }

        $emp3 = Employee::where('employee_code', 'EMP-003')->first();
        $hrManager = Employee::where('employee_code', 'EMP-002')->first();

        if ($emp3 && $hrManager) {
            PerformanceReview::firstOrCreate(
                ['employee_id' => $emp3->id, 'reviewer_id' => $hrManager->id, 'cycle_id' => $cycle->id],
                [
                    'rating_technical_skills' => 5,
                    'rating_communication' => 5,
                    'rating_teamwork' => 4,
                    'rating_leadership' => 4,
                    'rating_productivity' => 4,
                    'rating_problem_solving' => 3,
                    'rating_attendance' => 5,
                    'rating_goal_achievement' => 5,
                    'comments' => 'Excellent work handling recruitment and onboarding. Solid teamwork.',
                    'status' => 'submitted'
                ]
            );
        }
    }
}
