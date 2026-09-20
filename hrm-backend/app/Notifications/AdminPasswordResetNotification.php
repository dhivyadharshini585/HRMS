<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetNotification extends Notification
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
        $resetUrl = $frontendUrl . '/admin-reset-password?token=' . urlencode($this->token);

        $name = isset($notifiable->name) ? $notifiable->name : 'Administrator';

        return (new MailMessage)
            ->subject('Admin Password Reset Request')
            ->greeting('Hello ' . $name . ',')
            ->line('You are receiving this email because a password reset request was submitted for your HRMS administrator account.')
            ->line('Click the button below to reset your password. This link is single-use and will expire in 15 minutes.')
            ->action('Reset Admin Password', $resetUrl)
            ->line('If you did not request a password reset, please ignore this email or contact security immediately.');
    }
}
