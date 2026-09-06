<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestManagerApprovedNotification extends Notification
{
    use Queueable;

    public LeaveRequest $leaveRequest;
    public string $recipientRole; // 'employee' or 'hr'

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest, string $recipientRole = 'employee')
    {
        $this->leaveRequest = $leaveRequest;
        $this->recipientRole = $recipientRole;
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
        $empName = $emp ? "{$emp->first_name} {$emp->last_name}" : 'Employee';
        $leaveTypeName = $this->leaveRequest->leaveType?->name ?? 'Leave';

        if ($this->recipientRole === 'hr') {
            return [
                'type' => 'leave_manager_approved_hr',
                'leave_request_id' => $this->leaveRequest->id,
                'employee_id' => $this->leaveRequest->employee_id,
                'employee_name' => $empName,
                'leave_type' => $leaveTypeName,
                'from_date' => $this->leaveRequest->from_date,
                'to_date' => $this->leaveRequest->to_date,
                'number_of_days' => $this->leaveRequest->number_of_days,
                'title' => 'Leave Request Awaiting HR Final Approval',
                'message' => "Manager approved {$leaveTypeName} request #{$this->leaveRequest->id} for {$empName}. Awaiting HR final approval.",
            ];
        }

        return [
            'type' => 'leave_manager_approved_employee',
            'leave_request_id' => $this->leaveRequest->id,
            'employee_id' => $this->leaveRequest->employee_id,
            'employee_name' => $empName,
            'leave_type' => $leaveTypeName,
            'from_date' => $this->leaveRequest->from_date,
            'to_date' => $this->leaveRequest->to_date,
            'number_of_days' => $this->leaveRequest->number_of_days,
            'title' => 'Leave Request Approved by Manager',
            'message' => "Your {$leaveTypeName} request #{$this->leaveRequest->id} has been approved by your manager and forwarded to HR for final approval.",
        ];
    }
}
