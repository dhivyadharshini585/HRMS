<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatutoryPayrollRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'rule_type',
        'base_component',
        'percentage',
        'fixed_amount',
        'slabs',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'percentage' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'slabs' => 'array',
    ];
}
