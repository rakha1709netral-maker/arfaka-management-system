<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArfakaPasswordResetNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $url = rtrim((string) config('app.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return (new MailMessage)
            ->subject('Reset Password | ARFAKA TOUR')
            ->greeting('Halo, '.$notifiable->name)
            ->line('Kami menerima permintaan untuk mengatur ulang password akun ARFAKA TOUR Anda.')
            ->action('Reset Password', $url)
            ->line('Tautan ini berlaku selama 60 menit dan hanya dapat digunakan sekali.')
            ->line('Abaikan email ini jika Anda tidak meminta reset password.');
    }
}
