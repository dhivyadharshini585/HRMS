<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'employee_id', 'problem', 'category',
        'priority', 'status', 'assigned_to', 'resolution_notes',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public static function generateTicketNumber(): string
    {
        $maxTicket = static::max('ticket_number');
        $next = $maxTicket ? ((int) substr($maxTicket, 3)) + 1 : 1;

        return 'IT-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
