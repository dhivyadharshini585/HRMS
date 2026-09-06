<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadResumeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Controller will handle permission check via policy / Spatie permission
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'resume' => [
                'required',
                'file',
                'max:10240', // 10MB in kilobytes
                'mimes:pdf,doc,docx',
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'resume' => 'resume file',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'resume.required' => 'Please select a resume file to upload.',
            'resume.file' => 'The uploaded resume must be a valid file.',
            'resume.max' => 'The resume size must not exceed 10 MB.',
            'resume.mimes' => 'The resume must be a file of type: PDF, DOC, or DOCX.',
        ];
    }
}
