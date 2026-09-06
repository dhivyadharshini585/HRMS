<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondOfferLetterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.offer_letters.respond') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $isReject = $this->is('*/reject');

        return [
            'response_remarks' => [$isReject ? 'required' : 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'response_remarks.required' => 'Response remarks / reason are required when rejecting an offer letter.',
        ];
    }
}
