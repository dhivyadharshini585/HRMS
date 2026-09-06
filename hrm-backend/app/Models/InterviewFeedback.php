<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterviewFeedback extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'interview_feedback';

    protected $fillable = [
        'interview_id',
        'interviewer_employee_id',
        'overall_rating',
        'technical_rating',
        'communication_rating',
        'problem_solving_rating',
        'cultural_fit_rating',
        'strengths',
        'weaknesses',
        'comments',
        'recommendation',
        'submitted_at',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'technical_rating' => 'integer',
        'communication_rating' => 'integer',
        'problem_solving_rating' => 'integer',
        'cultural_fit_rating' => 'integer',
        'submitted_at' => 'datetime',
    ];

    /**
     * The interview this feedback belongs to.
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class, 'interview_id');
    }

    /**
     * The interviewer employee who provided this feedback.
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_employee_id');
    }
}
