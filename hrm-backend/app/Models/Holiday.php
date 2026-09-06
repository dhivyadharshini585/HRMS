<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'holiday_date',
        'description',
        'is_active',
        'holiday_type',
    ];

    protected $casts = [
        'holiday_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];
}
