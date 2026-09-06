<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInterviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'candidate_id' => ['sometimes', 'integer', 'exists:candidates,id'],
            'job_opening_id' => ['sometimes', 'integer', 'exists:job_openings,id'],
            'interviewer_employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'interview_type' => ['sometimes', 'string', 'in:HR,Technical,Managerial,Final'],
            'interview_round' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'scheduled_at' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'mode' => ['sometimes', 'string', 'in:Online,In-person,Phone'],
            'location_or_link' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', 'in:Scheduled,Completed,Cancelled,Rescheduled'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Configure the validator instance with business logic checks.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $interviewId = $this->route('id') ?? $this->route('interview');
            $currentInterview = $interviewId ? Interview::find($interviewId) : null;

            $candidateId = $this->input('candidate_id', $currentInterview?->candidate_id);
            $jobOpeningId = $this->input('job_opening_id', $currentInterview?->job_opening_id);

            // 1. Verify candidate belongs to job opening
            if ($candidateId && $jobOpeningId) {
                $candidate = Candidate::find($candidateId);
                if ($candidate && (int) $candidate->job_opening_id !== (int) $jobOpeningId) {
                    $validator->errors()->add(
                        'job_opening_id',
                        'The selected job opening does not match the candidate’s assigned job opening.'
                    );
                }
            }

            // 2. Verify interviewer employee exists and is active
            $interviewerId = $this->input('interviewer_employee_id', $currentInterview?->interviewer_employee_id);
            if ($this->has('interviewer_employee_id') && $interviewerId) {
                $interviewer = Employee::find($interviewerId);
                if ($interviewer && $interviewer->employment_status !== 'Active') {
                    $validator->errors()->add(
                        'interviewer_employee_id',
                        'The selected interviewer is not an active employee.'
                    );
                }
            }

            // 3. Verify scheduling conflict if timing or interviewer changed
            $scheduledAt = $this->input('scheduled_at', $currentInterview?->scheduled_at);
            $durationMinutes = $this->input('duration_minutes', $currentInterview?->duration_minutes);
            $targetStatus = $this->input('status', $currentInterview?->status);

            if ($interviewerId && $scheduledAt && $durationMinutes && in_array($targetStatus, ['Scheduled', 'Rescheduled'])) {
                try {
                    $start = Carbon::parse($scheduledAt);
                    $end = $start->copy()->addMinutes((int) $durationMinutes);

                    $conflict = Interview::where('interviewer_employee_id', $interviewerId)
                        ->whereIn('status', ['Scheduled', 'Rescheduled'])
                        ->when($currentInterview, fn($q) => $q->where('id', '!=', $currentInterview->id))
                        ->where(function ($q) use ($start, $end) {
                            $q->where('scheduled_at', '<', $end)
                              ->whereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start]);
                        })
                        ->exists();

                    if ($conflict) {
                        $validator->errors()->add(
                            'scheduled_at',
                            'The selected interviewer already has a scheduled interview during this time slot.'
                        );
                    }
                } catch (\Throwable $e) {
                    // Carbon parse failure handled by date rule
                }
            }
        });
    }

    /**
     * Custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'candidate_id' => 'candidate',
            'job_opening_id' => 'job opening',
            'interviewer_employee_id' => 'interviewer',
            'interview_type' => 'interview type',
            'interview_round' => 'round',
            'scheduled_at' => 'schedule date and time',
            'duration_minutes' => 'duration',
            'mode' => 'interview mode',
            'location_or_link' => 'location or meeting link',
            'status' => 'status',
        ];
    }
}
