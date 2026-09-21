<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:Male,Female,Other',
            'date_of_joining' => 'required|date',
            'department_id' => 'nullable|exists:departments,id',
            'designation_id' => 'nullable|exists:designations,id',
            'employment_type' => 'required|in:Full-time,Part-time,Contract,Intern',
            'employment_status' => 'sometimes|in:Active,Inactive,On Leave,Terminated,Probation',
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'profile_photo_path' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'job_level' => 'nullable|string|max:50',
            'manager_id' => 'nullable|exists:employees,id',
            'work_location' => 'nullable|string|max:100',
            'work_shift' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An employee with this email already exists.',
            'user_id.unique' => 'This user account is already linked to another employee.',
            'department_id.exists' => 'The selected department does not exist.',
            'designation_id.exists' => 'The selected designation does not exist.',
        ];
    }
}
