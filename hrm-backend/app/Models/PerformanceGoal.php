<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceGoal extends Model
{
    protected $fillable = [
        'employee_id',
        'cycle_id',
        'goal',
        'target',
        'deadline',
        'progress',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'progress' => 'integer',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle()
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }
}
