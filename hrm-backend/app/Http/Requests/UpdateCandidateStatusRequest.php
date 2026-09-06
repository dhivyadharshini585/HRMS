<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->can('recruitment.candidates.update');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(['New', 'Screening', 'Shortlisted', 'Rejected', 'Hired']),
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn () => $this->input('status') === 'Rejected'),
            ],
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'remarks.required' => 'Rejection remarks are required when marking a candidate as Rejected.',
            'status.in' => 'The selected candidate status is invalid.',
        ];
    }
}
