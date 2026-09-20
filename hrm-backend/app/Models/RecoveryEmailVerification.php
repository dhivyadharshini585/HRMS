<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryEmailVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recovery_email',
        'token_hash',
        'expires_at',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the recovery email verification request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
