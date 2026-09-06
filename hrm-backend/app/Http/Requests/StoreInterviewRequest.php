<?php

namespace App\Http\Requests;

use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreInterviewRequest extends FormRequest
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
            'candidate_id' => ['required', 'integer', 'exists:candidates,id'],
            'job_opening_id' => ['required', 'integer', 'exists:job_openings,id'],
            'interviewer_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'interview_type' => ['required', 'string', 'in:HR,Technical,Managerial,Final'],
            'interview_round' => ['nullable', 'integer', 'min:1', 'max:20'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'mode' => ['required', 'string', 'in:Online,In-person,Phone'],
            'location_or_link' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Configure the validator instance with business logic checks.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // 1. Verify candidate belongs to the specified job opening
            if ($this->filled('candidate_id') && $this->filled('job_opening_id')) {
                $candidate = Candidate::find($this->candidate_id);
                if ($candidate && (int) $candidate->job_opening_id !== (int) $this->job_opening_id) {
                    $validator->errors()->add(
                        'job_opening_id',
                        'The selected job opening does not match the candidate’s assigned job opening.'
                    );
                }
            }

            // 2. Verify interviewer employee exists and is active
            if ($this->filled('interviewer_employee_id')) {
                $interviewer = Employee::find($this->interviewer_employee_id);
                if ($interviewer && $interviewer->employment_status !== 'Active') {
                    $validator->errors()->add(
                        'interviewer_employee_id',
                        'The selected interviewer is not an active employee.'
                    );
                }
            }

            // 3. Verify no scheduling conflict for the same interviewer
            if ($this->filled('interviewer_employee_id') && $this->filled('scheduled_at') && $this->filled('duration_minutes')) {
                try {
                    $start = Carbon::parse($this->scheduled_at);
                    $end = $start->copy()->addMinutes((int) $this->duration_minutes);

                    $conflict = Interview::where('interviewer_employee_id', $this->interviewer_employee_id)
                        ->whereIn('status', ['Scheduled', 'Rescheduled'])
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
        ];
    }
}
