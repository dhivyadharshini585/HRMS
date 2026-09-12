<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Onboarding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'candidate_id',
        'offer_letter_id',
        'employee_id',
        'joining_date',
        'status',
        'it_account_status',
        'it_account_remarks',
        'laptop_allocation_status',
        'laptop_allocation_remarks',
        'started_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'joining_date' => 'date:Y-m-d',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'progress_percentage',
    ];

    /**
     * Calculate progress percentage based on completed checklist items + IT/Laptop
     */
    public function getProgressPercentageAttribute(): int
    {
        $items = $this->checklistItems;
        if ($items->isEmpty()) return 0;
        
        $totalItems = $items->count() + 2; // +2 for IT and Laptop
        $completedItems = $items->whereIn('status', ['Verified', 'Not Applicable'])->count();
        
        if (in_array($this->it_account_status, ['Completed', 'Not Applicable'])) $completedItems++;
        if (in_array($this->laptop_allocation_status, ['Completed', 'Not Applicable'])) $completedItems++;

        return (int) round(($completedItems / $totalItems) * 100);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function offerLetter(): BelongsTo
    {
        return $this->belongsTo(OfferLetter::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(OnboardingChecklistItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
