<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'onboarding_id',
        'checklist_item_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_at',
        'uploaded_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'file_size' => 'integer',
    ];

    protected $hidden = [
        'file_path',
    ];

    /**
     * Get the onboarding process this document belongs to.
     */
    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(Onboarding::class);
    }

    /**
     * Get the specific checklist item this document is uploaded for.
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklistItem::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
