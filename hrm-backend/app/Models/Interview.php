<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Interview extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'candidate_id',
        'job_opening_id',
        'interviewer_employee_id',
        'interview_type',
        'interview_round',
        'scheduled_at',
        'duration_minutes',
        'mode',
        'location_or_link',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'interview_round' => 'integer',
    ];

    /**
     * Get the candidate being interviewed.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the job opening associated with this interview.
     */
    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    /**
     * Get the employee conducting the interview.
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_employee_id');
    }

    /**
     * Get the user who scheduled/created this interview.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the feedback record for this interview.
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(InterviewFeedback::class, 'interview_id');
    }

    /**
     * Scope query to filter by candidate ID.
     */
    public function scopeCandidate($query, $candidateId)
    {
        if (!empty($candidateId)) {
            $query->where('candidate_id', $candidateId);
        }
        return $query;
    }

    /**
     * Scope query to filter by job opening ID.
     */
    public function scopeJobOpening($query, $jobOpeningId)
    {
        if (!empty($jobOpeningId)) {
            $query->where('job_opening_id', $jobOpeningId);
        }
        return $query;
    }

    /**
     * Scope query to filter by interviewer employee ID.
     */
    public function scopeInterviewer($query, $interviewerId)
    {
        if (!empty($interviewerId)) {
            $query->where('interviewer_employee_id', $interviewerId);
        }
        return $query;
    }

    /**
     * Scope query to filter by status.
     */
    public function scopeStatus($query, $status)
    {
        if (!empty($status)) {
            $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope query to filter by interview type.
     */
    public function scopeInterviewType($query, $type)
    {
        if (!empty($type)) {
            $query->where('interview_type', $type);
        }
        return $query;
    }

    /**
     * Scope query to filter by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        if (!empty($from)) {
            $query->whereDate('scheduled_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('scheduled_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope query to search across candidate, interviewer, or job opening details.
     */
    public function scopeSearch($query, $term)
    {
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->whereHas('candidate', function ($cq) use ($term) {
                    $cq->where('first_name', 'like', "%{$term}%")
                       ->orWhere('last_name', 'like', "%{$term}%")
                       ->orWhere('candidate_code', 'like', "%{$term}%")
                       ->orWhere('email', 'like', "%{$term}%");
                })
                ->orWhereHas('interviewer', function ($iq) use ($term) {
                    $iq->where('first_name', 'like', "%{$term}%")
                       ->orWhere('last_name', 'like', "%{$term}%")
                       ->orWhere('employee_code', 'like', "%{$term}%");
                })
                ->orWhereHas('jobOpening', function ($jq) use ($term) {
                    $jq->where('title', 'like', "%{$term}%")
                       ->orWhere('job_code', 'like', "%{$term}%");
                });
            });
        }
        return $query;
    }
}
