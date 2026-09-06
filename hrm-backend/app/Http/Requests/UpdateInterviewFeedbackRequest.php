<?php

namespace App\Http\Requests;

use App\Models\Interview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInterviewFeedbackRequest extends FormRequest
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

        // Must have update permission
        if (!$user->can('recruitment.interview_feedback.update')) {
            return false;
        }

        $interview = $this->getInterviewModel();
        if (!$interview) {
            return true;
        }

        // Manager interviewer-ownership restriction:
        // Managers can UPDATE feedback ONLY when they are the designated interviewer.
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
            'overall_rating' => ['sometimes', 'required', 'integer', 'between:1,5'],
            'technical_rating' => ['nullable', 'integer', 'between:1,5'],
            'communication_rating' => ['nullable', 'integer', 'between:1,5'],
            'problem_solving_rating' => ['nullable', 'integer', 'between:1,5'],
            'cultural_fit_rating' => ['nullable', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'weaknesses' => ['nullable', 'string', 'max:5000'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['sometimes', 'required', 'string', Rule::in(['Strong Hire', 'Hire', 'Hold', 'No Hire'])],
        ];
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
