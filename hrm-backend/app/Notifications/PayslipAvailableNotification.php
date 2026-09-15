<?php

namespace App\Notifications;

use App\Models\Payslip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Carbon\Carbon;

class PayslipAvailableNotification extends Notification
{
    use Queueable;

    public Payslip $payslip;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payslip $payslip)
    {
        $this->payslip = $payslip;
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
        $payroll = $this->payslip->payroll;
        $monthName = Carbon::createFromDate($payroll->year, $payroll->month, 1)->format('F');

        return [
            'type' => 'payslip_available',
            'payslip_id' => $this->payslip->id,
            'payroll_id' => $payroll->id,
            'title' => 'Payslip Available',
            'message' => "Your payslip for {$monthName} {$payroll->year} is now available for download.",
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payroll = $this->payslip->payroll;
        $monthName = Carbon::createFromDate($payroll->year, $payroll->month, 1)->format('F');

        return (new MailMessage)
                    ->subject('Payslip Available')
                    ->line("Your payslip for {$monthName} {$payroll->year} is now available for download.")
                    ->line('Please log in to the HRMS portal to download your payslip.');
    }
}
