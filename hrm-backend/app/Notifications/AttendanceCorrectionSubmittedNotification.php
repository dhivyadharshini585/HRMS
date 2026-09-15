<?php

namespace App\Notifications;

use App\Models\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceCorrectionSubmittedNotification extends Notification
{
    use Queueable;

    public AttendanceCorrection $correction;

    /**
     * Create a new notification instance.
     */
    public function __construct(AttendanceCorrection $correction)
    {
        $this->correction = $correction;
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
        $emp = $this->correction->employee;
        $empName = $emp ? "{$emp->first_name} {$emp->last_name}" : 'An employee';
        $attendanceDate = $this->correction->attendance->attendance_date;

        return [
            'type' => 'attendance_correction_submitted',
            'correction_id' => $this->correction->id,
            'employee_id' => $this->correction->employee_id,
            'employee_name' => $empName,
            'attendance_date' => $attendanceDate,
            'title' => 'New Attendance Correction Request',
            'message' => "{$empName} submitted an attendance correction request for {$attendanceDate}.",
        ];
    }
}
