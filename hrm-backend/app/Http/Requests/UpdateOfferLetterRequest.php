<?php

namespace App\Http\Requests;

use App\Models\OfferLetter;
use App\Services\OfferLetterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateOfferLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.offer_letters.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'candidate_id' => ['sometimes', 'required', 'integer', 'exists:candidates,id'],
            'job_opening_id' => ['sometimes', 'required', 'integer', 'exists:job_openings,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'offer_date' => ['sometimes', 'required', 'date'],
            'joining_date' => ['sometimes', 'required', 'date', 'after_or_equal:offer_date'],
            'designation' => ['sometimes', 'required', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'required', 'in:Full-time,Part-time,Contract,Internship'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'salary_amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:999999999.99'],
            'salary_currency' => ['sometimes', 'required', 'string', 'max:10'],
            'salary_frequency' => ['sometimes', 'required', 'in:Annual,Monthly,Bi-weekly,Weekly,Hourly'],
            'probation_period_months' => ['nullable', 'integer', 'min:0', 'max:36'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $offerId = $this->route('id') ?? $this->route('offer_letter');
            $offer = OfferLetter::find($offerId);

            if ($offer && $offer->offer_status !== 'Draft') {
                $validator->errors()->add('offer_status', "Cannot edit offer letter: Current status is '{$offer->offer_status}'. Only 'Draft' offers may be edited.");
                return;
            }

            $candidateId = $this->filled('candidate_id') ? (int) $this->candidate_id : ($offer ? $offer->candidate_id : null);
            $jobOpeningId = $this->filled('job_opening_id') ? (int) $this->job_opening_id : ($offer ? $offer->job_opening_id : null);

            if ($candidateId && $jobOpeningId) {
                try {
                    $service = app(OfferLetterService::class);
                    $service->validateCandidateEligibility(
                        $candidateId,
                        $jobOpeningId,
                        $offer ? $offer->id : null
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
