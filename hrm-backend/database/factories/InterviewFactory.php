<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        return [
            'candidate_id'    => Candidate::factory(),
            'job_opening_id'  => function (array $attributes) {
                return Candidate::find($attributes['candidate_id'])->job_opening_id;
            },
            'interviewer_employee_id' => function () {
                $emp = \App\Models\Employee::first();
                if (!$emp) {
                    $user = \App\Models\User::factory()->create();
                    $dept = \App\Models\Department::first() ?? \App\Models\Department::create(['name' => 'HR', 'code' => 'HR']);
                    $desig = \App\Models\Designation::first() ?? \App\Models\Designation::create(['title' => 'Manager', 'code' => 'MGR', 'department_id' => $dept->id]);
                    $emp = \App\Models\Employee::create([
                        'user_id' => $user->id,
                        'employee_code' => 'EMP-' . rand(1000, 9999),
                        'first_name' => 'Test',
                        'last_name' => 'Interviewer',
                        'email' => $user->email,
                        'department_id' => $dept->id,
                        'designation_id' => $desig->id,
                        'employment_status' => 'Active',
                        'employment_type' => 'Full-time',
                        'date_of_joining' => now(),
                    ]);
                }
                return $emp->id;
            },
            'interview_type'  => $this->faker->randomElement(['Technical', 'HR', 'Managerial', 'Final']),
            'interview_round' => $this->faker->numberBetween(1, 3),
            'scheduled_at'    => now()->addDays($this->faker->numberBetween(1, 30)),
            'duration_minutes'=> $this->faker->randomElement([30, 45, 60, 90]),
            'mode'            => $this->faker->randomElement(['Online', 'In-person', 'Phone']),
            'status'          => 'Scheduled',
        ];
    }
}
