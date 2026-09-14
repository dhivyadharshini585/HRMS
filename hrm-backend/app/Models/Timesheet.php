<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'project_id', 'task_id', 'date', 'hours',
        'billable_hours', 'non_billable_hours', 'client',
        'manager_id', 'status', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'billable_hours' => 'decimal:2',
        'non_billable_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
