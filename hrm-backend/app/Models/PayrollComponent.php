<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'component_name',
        'component_type',
        'component_source',
        'amount',
        'statutory_payroll_rule_id',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function statutoryRule(): BelongsTo
    {
        return $this->belongsTo(StatutoryPayrollRule::class, 'statutory_payroll_rule_id');
    }
}
