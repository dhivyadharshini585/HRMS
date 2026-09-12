<?php

namespace Database\Factories;

use App\Models\OfferLetter;
use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferLetterFactory extends Factory
{
    protected $model = OfferLetter::class;

    public function definition(): array
    {
        return [
            'offer_code' => 'OFF-' . date('Y') . '-' . $this->faker->unique()->numberBetween(100000, 999999),
            'candidate_id' => function () {
                return Candidate::factory()->create()->id;
            },
            'job_opening_id' => function (array $attributes) {
                return Candidate::find($attributes['candidate_id'])->job_opening_id;
            },
            'department_id' => function (array $attributes) {
                return JobOpening::find($attributes['job_opening_id'])->department_id;
            },
            'offer_date' => now()->toDateString(),
            'joining_date' => now()->addWeeks(2)->toDateString(),
            'designation' => 'Developer',
            'employment_type' => 'Full-time',
            'work_location' => 'Remote',
            'salary_amount' => 100000.00,
            'salary_currency' => 'USD',
            'salary_frequency' => 'Annual',
            'probation_period_months' => 3,
            'notice_period_days' => 30,
            'offer_status' => 'Draft',
        ];
    }
}
