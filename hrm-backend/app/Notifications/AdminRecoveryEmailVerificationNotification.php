<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminRecoveryEmailVerificationNotification extends Notification
{
    use Queueable;

    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')), '/');
        $verifyUrl = $frontendUrl . '/verify-recovery-email?token=' . urlencode($this->token);

        $name = isset($notifiable->name) ? $notifiable->name : 'Administrator';

        return (new MailMessage)
            ->subject('Verify Your HRMS Admin Recovery Email')
            ->greeting('Hello ' . $name . ',')
            ->line('You have configured this email address as a recovery email for your HRMS administrator account.')
            ->line('Please click the button below to verify this recovery email address. This verification link will expire in 24 hours.')
            ->action('Verify Recovery Email', $verifyUrl)
            ->line('If you did not initiate this request, no further action is required.');
    }
}
