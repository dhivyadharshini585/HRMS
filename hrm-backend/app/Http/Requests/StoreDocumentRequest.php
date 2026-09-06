<?php

namespace App\Http\Requests;

use App\Constants\DocumentCategories;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('documents.create') ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        // If the user does not have permission to manage other employees' documents (i.e. not Super Admin / HR Admin / HR Executive),
        // force employee_id to the user's own employee record ID.
        if (!$user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            $employeeId = $user->employee?->id;
            $this->merge([
                'employee_id' => $employeeId,
            ]);
        }

        if (!$this->has('document_name') && $this->hasFile('file')) {
            $this->merge([
                'document_name' => pathinfo($this->file('file')->getClientOriginalName(), PATHINFO_FILENAME),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'document_category' => ['required', 'string', 'in:' . implode(',', DocumentCategories::getCategories())],
            'document_type' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $category = $this->input('document_category');
                    if ($category && !DocumentCategories::isValidType($category, $value)) {
                        $fail("The selected document type '{$value}' is invalid for the category '{$category}'.");
                    }
                },
            ],
            'document_name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp'],
        ];
    }
}
