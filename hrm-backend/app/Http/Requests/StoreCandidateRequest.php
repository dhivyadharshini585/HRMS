<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'candidate_code' => ['nullable', 'string', 'max:50', Rule::unique('candidates', 'candidate_code')],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('candidates', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'highest_qualification' => ['nullable', 'string', 'max:255'],
            'total_experience_years' => ['nullable', 'numeric', 'min:0'],
            'current_company' => ['nullable', 'string', 'max:255'],
            'current_designation' => ['nullable', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:100'],
            'job_opening_id' => ['required', 'integer', 'exists:job_openings,id'],
            'status' => ['required', 'string', 'in:New,Screening,Shortlisted,Rejected,Hired'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
