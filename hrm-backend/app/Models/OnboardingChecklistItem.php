<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_id',
        'document_id',
        'item_type',
        'status',
        'remarks',
        'verified_by',
        'verified_at',
        'completed_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the onboarding process this item belongs to.
     */
    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(Onboarding::class);
    }

    /**
     * Get the document uploaded for this checklist item.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(OnboardingDocument::class, 'document_id');
    }

    /**
     * Get the user who verified this item.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
