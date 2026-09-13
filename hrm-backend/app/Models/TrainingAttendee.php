<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingAttendee extends Model
{
    protected $fillable = [
        'training_id',
        'employee_id',
        'completion_status',
    ];

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
