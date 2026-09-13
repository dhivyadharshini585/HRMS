<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    protected $fillable = [
        'cycle_id',
        'employee_id',
        'reviewer_id',
        'rating_technical_skills',
        'rating_communication',
        'rating_teamwork',
        'rating_leadership',
        'rating_productivity',
        'rating_problem_solving',
        'rating_attendance',
        'rating_goal_achievement',
        'comments',
        'status',
    ];

    public function cycle()
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
