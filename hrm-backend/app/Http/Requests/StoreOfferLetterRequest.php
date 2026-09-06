<?php

namespace App\Http\Requests;

use App\Services\OfferLetterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreOfferLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.offer_letters.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'job_opening_id' => ['required', 'integer', 'exists:job_openings,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'offer_date' => ['required', 'date'],
            'joining_date' => ['required', 'date', 'after_or_equal:offer_date'],
            'designation' => ['required', 'string', 'max:255'],
            'employment_type' => ['required', 'in:Full-time,Part-time,Contract,Internship'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'salary_amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'salary_currency' => ['required', 'string', 'max:10'],
            'salary_frequency' => ['required', 'in:Annual,Monthly,Bi-weekly,Weekly,Hourly'],
            'probation_period_months' => ['nullable', 'integer', 'min:0', 'max:36'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Configure the validator instance with candidate and active offer verification.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('candidate_id') && $this->filled('job_opening_id')) {
                try {
                    $service = app(OfferLetterService::class);
                    $service->validateCandidateEligibility(
                        (int) $this->candidate_id,
                        (int) $this->job_opening_id
                    );
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($key, $message);
                        }
                    }
                }
            }
        });
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'joining_date.after_or_equal' => 'The joining date must not be earlier than the offer date.',
            'salary_amount.gt' => 'The salary amount must be greater than zero.',
        ];
    }
}
