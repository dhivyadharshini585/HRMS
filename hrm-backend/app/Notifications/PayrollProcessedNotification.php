<?php

namespace App\Notifications;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Carbon\Carbon;

class PayrollProcessedNotification extends Notification
{
    use Queueable;

    public Payroll $payroll;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payroll $payroll)
    {
        $this->payroll = $payroll;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $monthName = Carbon::createFromDate($this->payroll->year, $this->payroll->month, 1)->format('F');

        return [
            'type' => 'payroll_processed',
            'payroll_id' => $this->payroll->id,
            'title' => 'Payroll Processed',
            'message' => "Your payroll for {$monthName} {$this->payroll->year} has been successfully processed.",
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $monthName = Carbon::createFromDate($this->payroll->year, $this->payroll->month, 1)->format('F');

        return (new MailMessage)
                    ->subject('Payroll Processed')
                    ->line("Your payroll for {$monthName} {$this->payroll->year} has been successfully processed.")
                    ->line('Please log in to the HRMS portal to view your payroll details.');
    }
}
