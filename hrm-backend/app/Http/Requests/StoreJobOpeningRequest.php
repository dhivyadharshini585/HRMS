<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'job_code' => ['nullable', 'string', 'max:50', 'unique:job_openings,job_code'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'employment_type' => ['required', 'string', 'in:Full Time,Part Time,Contract,Internship'],
            'location' => ['required', 'string', 'max:255'],
            'openings_count' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:10000'],
            'requirements' => ['nullable', 'string', 'max:10000'],
            'responsibilities' => ['nullable', 'string', 'max:10000'],
            'experience_min' => ['nullable', 'integer', 'min:0'],
            'experience_max' => ['nullable', 'integer', 'min:0', 'gte:experience_min'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'application_deadline' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:Draft,Open,Closed'],
        ];
    }
}
