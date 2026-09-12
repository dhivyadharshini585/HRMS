<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'candidate_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'date_of_birth',
        'gender',
        'current_location',
        'address',
        'highest_qualification',
        'total_experience_years',
        'current_company',
        'current_designation',
        'expected_salary',
        'notice_period_days',
        'source',
        'job_opening_id',
        'status',
        'notes',
        'created_by',
        'resume_path',
        'resume_original_name',
        'resume_mime_type',
        'resume_size',
        'resume_uploaded_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'total_experience_years' => 'decimal:1',
        'expected_salary' => 'decimal:2',
        'notice_period_days' => 'integer',
        'resume_size' => 'integer',
        'resume_uploaded_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
        'resume',
    ];

    /**
     * Get safe resume metadata structure.
     */
    public function getResumeAttribute(): array
    {
        return [
            'exists' => !empty($this->resume_path),
            'original_name' => $this->resume_original_name,
            'mime_type' => $this->resume_mime_type,
            'size' => $this->resume_size ? (int) $this->resume_size : null,
            'uploaded_at' => $this->resume_uploaded_at?->toIso8601String(),
            'download_available' => !empty($this->resume_path),
        ];
    }

    /**
     * Get the candidate's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the job opening the candidate applied to.
     */
    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    /**
     * Get the user who recorded this candidate.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to search across candidate name, code, email, and phone.
     */
    public function scopeSearch($query, $term)
    {
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('candidate_code', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }
        return $query;
    }

    /**
     * Scope query by status.
     */
    public function scopeStatus($query, $status)
    {
        if (!empty($status)) {
            $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope query by job opening ID.
     */
    public function scopeJobOpening($query, $jobOpeningId)
    {
        if (!empty($jobOpeningId)) {
            $query->where('job_opening_id', $jobOpeningId);
        }
        return $query;
    }

    /**
     * Scope query by source.
     */
    public function scopeSource($query, $source)
    {
        if (!empty($source)) {
            $query->where('source', $source);
        }
        return $query;
    }

    /**
     * Get the interviews scheduled for this candidate.
     */
    public function interviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * Get the status history timeline for this candidate.
     */
    public function statusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CandidateStatusHistory::class)->orderBy('changed_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Get all offer letters issued to this candidate.
     */
    public function offerLetters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OfferLetter::class);
    }

    /**
     * Get the currently active offer letter for this candidate (Draft or Sent).
     */
    public function activeOfferLetter(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OfferLetter::class)->whereIn('offer_status', ['Draft', 'Sent']);
    }

    /**
     * Get the onboarding process for this candidate.
     */
    public function onboarding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Onboarding::class);
    }

    /**
     * The "booted" method of the model.
     * Automatically and atomically records initial status history on creation.
     */
    protected static function booted(): void
    {
        static::created(function (Candidate $candidate) {
            $candidate->statusHistories()->create([
                'from_status' => null,
                'to_status' => $candidate->status ?? 'New',
                'changed_by' => auth()->id() ?? $candidate->created_by,
                'remarks' => 'Initial candidate profile created',
                'changed_at' => $candidate->created_at ?? now(),
            ]);
        });
    }
}
