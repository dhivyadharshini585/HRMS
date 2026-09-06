<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestRejectedNotification extends Notification
{
    use Queueable;

    public LeaveRequest $leaveRequest;
    public ?string $rejectionReason;
    public string $rejectedByRole; // 'Manager' or 'HR'

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest, ?string $rejectionReason = null, string $rejectedByRole = 'Manager')
    {
        $this->leaveRequest = $leaveRequest;
        $this->rejectionReason = $rejectionReason;
        $this->rejectedByRole = $rejectedByRole;
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
        $leaveTypeName = $this->leaveRequest->leaveType?->name ?? 'Leave';
        $reasonText = $this->rejectionReason ? " Reason: {$this->rejectionReason}" : '';

        return [
            'type' => 'leave_rejected',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_id' => $this->leaveRequest->employee_id,
            'leave_type' => $leaveTypeName,
            'from_date' => $this->leaveRequest->from_date,
            'to_date' => $this->leaveRequest->to_date,
            'number_of_days' => $this->leaveRequest->number_of_days,
            'status' => 'Rejected',
            'rejected_by' => $this->rejectedByRole,
            'rejection_reason' => $this->rejectionReason,
            'title' => "Leave Request Rejected by {$this->rejectedByRole}",
            'message' => "Your {$leaveTypeName} request #{$this->leaveRequest->id} was rejected by {$this->rejectedByRole}.{$reasonText}",
        ];
    }
}
