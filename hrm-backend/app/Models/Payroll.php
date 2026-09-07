<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'basic_salary',
        'gross_earnings',
        'total_deductions',
        'net_salary',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }
}
