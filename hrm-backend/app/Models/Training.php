<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'training_name',
        'trainer_employee_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function trainer()
    {
        return $this->belongsTo(Employee::class, 'trainer_employee_id');
    }

    public function attendees()
    {
        return $this->hasMany(TrainingAttendee::class, 'training_id');
    }
}
