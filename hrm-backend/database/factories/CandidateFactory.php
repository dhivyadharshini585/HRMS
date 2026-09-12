<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'candidate_code' => 'CAN-' . $this->faker->unique()->numberBetween(10000, 99999),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'status' => 'New',
            'job_opening_id' => function () {
                $job = JobOpening::first();
                if (!$job) {
                    $dept = Department::first() ?? Department::create(['name' => 'Engineering', 'description' => 'Eng']);
                    $desig = Designation::first() ?? Designation::create(['title' => 'Software Engineer', 'description' => 'SE']);
                    
                    $job = JobOpening::create([
                        'title' => 'Software Engineer',
                        'job_code' => 'JOB-' . rand(1000, 9999),
                        'department_id' => $dept->id,
                        'designation_id' => $desig->id,
                        'employment_type' => 'Full Time',
                        'location' => 'Remote',
                        'openings_count' => 1,
                        'description' => 'Software Engineer required',
                        'status' => 'Open',
                    ]);
                }
                return $job->id;
            },
        ];
    }
}
