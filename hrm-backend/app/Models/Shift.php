<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'is_active',
        'description',
        'grace_period_minutes',
        'overtime_enabled',
        'overtime_threshold_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'overtime_enabled' => 'boolean',
        'grace_period_minutes' => 'integer',
        'overtime_threshold_minutes' => 'integer',
    ];

    /**
     * Relationship to employees assigned to this shift.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
