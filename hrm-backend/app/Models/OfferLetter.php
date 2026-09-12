<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfferLetter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'offer_letters';

    protected $fillable = [
        'offer_code',
        'candidate_id',
        'job_opening_id',
        'department_id',
        'offer_date',
        'joining_date',
        'expiry_date',
        'designation',
        'employment_type',
        'work_location',
        'salary_amount',
        'salary_currency',
        'salary_frequency',
        'probation_period_months',
        'notice_period_days',
        'benefits',
        'terms_and_conditions',
        'offer_status',
        'sent_at',
        'responded_at',
        'response_remarks',
        'document_path',
        'document_original_name',
        'created_by',
    ];

    protected $casts = [
        'offer_date' => 'date:Y-m-d',
        'joining_date' => 'date:Y-m-d',
        'expiry_date' => 'date:Y-m-d',
        'salary_amount' => 'decimal:2',
        'probation_period_months' => 'integer',
        'notice_period_days' => 'integer',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected $hidden = [
        'document_path', // Never expose internal filesystem storage paths directly in API responses
    ];

    protected $appends = [
        'has_document',
        'document_name',
    ];

    /**
     * Determine if a generated document exists for this offer.
     */
    public function getHasDocumentAttribute(): bool
    {
        return !empty($this->document_path);
    }

    /**
     * Get safe display name of the offer letter document.
     */
    public function getDocumentNameAttribute(): ?string
    {
        return $this->document_original_name ?: ($this->offer_code ? "{$this->offer_code}.pdf" : null);
    }

    /**
     * Relationship: Candidate receiving this offer.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Relationship: Job opening associated with this offer.
     */
    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    /**
     * Relationship: Department associated with this offer.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship: User who created this offer letter.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to active offers (Draft or Sent).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('offer_status', ['Draft', 'Sent']);
    }

    /**
     * Scope query by status.
     */
    public function scopeStatus($query, $status)
    {
        if (!empty($status)) {
            $query->where('offer_status', $status);
        }
        return $query;
    }

    /**
     * Scope query by candidate ID.
     */
    public function scopeCandidate($query, $candidateId)
    {
        if (!empty($candidateId)) {
            $query->where('candidate_id', $candidateId);
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
     * Scope query by search term across code, designation, and candidate name/email.
     */
    public function scopeSearch($query, $search)
    {
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('offer_code', 'like', "%{$search}%")
                  ->orWhere('designation', 'like', "%{$search}%")
                  ->orWhereHas('candidate', function ($cq) use ($search) {
                      $cq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('candidate_code', 'like', "%{$search}%");
                  });
            });
        }
        return $query;
    }

    /**
     * Get the onboarding process associated with this offer letter.
     */
    public function onboarding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Onboarding::class);
    }
}
