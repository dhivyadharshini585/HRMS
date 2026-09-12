<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'profile_photo_path',
        'date_of_birth',
        'gender',
        'date_of_joining',
        'department_id',
        'designation_id',
        'shift_id',
        'job_level',
        'manager_id',
        'employment_type',
        'employment_status',
        'work_location',
        'work_shift',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
        ];
    }

    /**
     * Get the full name of the employee.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the user account associated with this employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department this employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation of this employee.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the assigned shift for this employee.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the manager of this employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Get the direct reports for this employee.
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * Get the employment history entries for this employee.
     */
    public function employmentHistories(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class);
    }

    /**
     * Get the documents for this employee.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Get the attendance records for this employee.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the shift rotations for this employee.
     */
    public function shiftRotations(): HasMany
    {
        return $this->hasMany(EmployeeShiftRotation::class);
    }

    /**
     * Get the leave balances for this employee.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get the leave requests for this employee.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the current active shift based on rotations or fallback to default shift.
     */
    public function currentShift()
    {
        $today = now()->toDateString();
        
        $activeRotation = $this->shiftRotations()
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereIn('status', ['Scheduled', 'Active'])
            ->first();

        if ($activeRotation) {
            return $activeRotation->shift;
        }

        return $this->shift;
    }

    /**
     * Generate the next employee code.
     */
    public static function generateEmployeeCode(): string
    {
        $lastEmployee = static::withTrashed()->orderByDesc('id')->first();

        if ($lastEmployee && preg_match('/EMP-(\d+)/', $lastEmployee->employee_code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return 'EMP-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the interviews conducted by this employee.
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'interviewer_employee_id');
    }

    /**
     * Get the interview feedbacks submitted by this employee.
     */
    public function interviewFeedbacks(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class, 'interviewer_employee_id');
    }

    /**
     * Get the onboarding record associated with this employee.
     */
    public function onboarding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Onboarding::class);
    }
}
