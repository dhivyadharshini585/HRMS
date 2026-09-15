<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'check_in',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_device',
        'check_out',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_device',
        'status',
        'working_minutes',
        'overtime_minutes',
        'early_exit_minutes',
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'check_in' => 'datetime',
        'check_in_latitude' => 'float',
        'check_in_longitude' => 'float',
        'check_out' => 'datetime',
        'check_out_latitude' => 'float',
        'check_out_longitude' => 'float',
        'working_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'early_exit_minutes' => 'integer',
    ];

    /**
     * Relationship to Employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
