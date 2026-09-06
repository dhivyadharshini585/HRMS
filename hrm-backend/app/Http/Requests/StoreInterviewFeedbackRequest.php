<?php

namespace App\Http\Requests;

use App\Models\Interview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterviewFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Must have permission
        if (!$user->can('recruitment.interview_feedback.create')) {
            return false;
        }

        $interview = $this->getInterviewModel();
        if (!$interview) {
            return true; // Let validation / model binding handle 404
        }

        // Manager interviewer-ownership restriction:
        // Managers can CREATE feedback ONLY when they are the designated interviewer.
        // Do NOT apply this restriction to Super Admin, HR Admin, or HR Executive.
        if ($user->hasRole('Manager') && !$user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            $managerEmployee = $user->employee;
            if (!$managerEmployee || (int) $managerEmployee->id !== (int) $interview->interviewer_employee_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'technical_rating' => ['nullable', 'integer', 'between:1,5'],
            'communication_rating' => ['nullable', 'integer', 'between:1,5'],
            'problem_solving_rating' => ['nullable', 'integer', 'between:1,5'],
            'cultural_fit_rating' => ['nullable', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'weaknesses' => ['nullable', 'string', 'max:5000'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['required', 'string', Rule::in(['Strong Hire', 'Hire', 'Hold', 'No Hire'])],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $interview = $this->getInterviewModel();
            if (!$interview) {
                $validator->errors()->add('interview_id', 'The specified interview does not exist.');
                return;
            }

            // 1. Feedback can only be submitted for completed interviews
            if ($interview->status !== 'Completed') {
                $validator->errors()->add('interview_id', 'Feedback can only be submitted for completed interviews.');
            }

            // 2. Prevent duplicate feedback for the same interview
            if ($interview->feedback()->exists()) {
                $validator->errors()->add('interview_id', 'Feedback has already been submitted for this interview.');
            }

            // 3. Interviewer must be an active employee
            if (!$interview->interviewer || $interview->interviewer->employment_status !== 'Active') {
                $validator->errors()->add('interviewer_employee_id', 'The assigned interviewer must be an active employee.');
            }
        });
    }

    /**
     * Helper to retrieve Interview model from route parameter.
     */
    protected function getInterviewModel(): ?Interview
    {
        $interviewParam = $this->route('interview') ?? $this->route('id');
        if ($interviewParam instanceof Interview) {
            return $interviewParam;
        }

        if (is_numeric($interviewParam)) {
            $interview = Interview::find($interviewParam);
            if (!$interview) {
                abort(404, 'The specified interview was not found.');
            }
            return $interview;
        }

        abort(404, 'The specified interview was not found.');
    }
}
