<?php

namespace App\Notifications;

use App\Models\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendanceCorrectionApprovedNotification extends Notification
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
        $attendanceDate = $this->correction->attendance->attendance_date;

        return [
            'type' => 'attendance_correction_approved',
            'correction_id' => $this->correction->id,
            'attendance_date' => $attendanceDate,
            'title' => 'Attendance Correction Approved',
            'message' => "Your attendance correction request for {$attendanceDate} has been approved.",
        ];
    }
}
