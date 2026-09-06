<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmittedNotification extends Notification
{
    use Queueable;

    public LeaveRequest $leaveRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $emp = $this->leaveRequest->employee;
        $empName = $emp ? "{$emp->first_name} {$emp->last_name}" : 'An employee';
        $leaveTypeName = $this->leaveRequest->leaveType?->name ?? 'Leave';

        return [
            'type' => 'leave_submitted',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_id' => $this->leaveRequest->employee_id,
            'employee_name' => $empName,
            'leave_type' => $leaveTypeName,
            'from_date' => $this->leaveRequest->from_date,
            'to_date' => $this->leaveRequest->to_date,
            'number_of_days' => $this->leaveRequest->number_of_days,
            'title' => 'New Leave Request Pending Approval',
            'message' => "{$empName} submitted a {$leaveTypeName} request ({$this->leaveRequest->number_of_days} days) for your review.",
        ];
    }
}
