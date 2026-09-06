<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_code',
        'title',
        'department_id',
        'designation_id',
        'employment_type',
        'location',
        'openings_count',
        'description',
        'requirements',
        'responsibilities',
        'experience_min',
        'experience_max',
        'salary_min',
        'salary_max',
        'application_deadline',
        'status',
        'created_by',
    ];

    protected $casts = [
        'openings_count' => 'integer',
        'experience_min' => 'integer',
        'experience_max' => 'integer',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
        'application_deadline' => 'date:Y-m-d',
    ];

    /**
     * Get the department that the job opening belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation that the job opening belongs to.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the user who created this job opening.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the candidates who applied to this job opening.
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /**
     * Scope query to search across title and job_code.
     */
    public function scopeSearch($query, $term)
    {
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('job_code', 'like', "%{$term}%")
                  ->orWhere('location', 'like', "%{$term}%");
            });
        }
        return $query;
    }

    /**
     * Scope query by status.
     */
    public function scopeStatus($query, $status)
    {
        if (!empty($status)) {
            $query->where('status', $status);
        }
        return $query;
    }

    /**
     * Scope query by department.
     */
    public function scopeDepartment($query, $departmentId)
    {
        if (!empty($departmentId)) {
            $query->where('department_id', $departmentId);
        }
        return $query;
    }

    /**
     * Scope query by employment type.
     */
    public function scopeEmploymentType($query, $type)
    {
        if (!empty($type)) {
            $query->where('employment_type', $type);
        }
        return $query;
    }

    /**
     * Get all interviews conducted for this job opening.
     */
    public function interviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * Get all offer letters issued for this job opening.
     */
    public function offerLetters(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OfferLetter::class);
    }
}
