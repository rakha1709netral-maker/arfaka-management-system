<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TourLeaderInvitationNotification extends Notification
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
        $url = rtrim((string) config('app.url'), '/').route('invitation.edit', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return (new MailMessage)
            ->subject('Undangan Akun Tour Leader | ARFAKA TOUR')
            ->greeting('Halo, '.$notifiable->name)
            ->line('Akun Tour Leader ARFAKA TOUR untuk Anda telah dibuat.')
            ->line('Silakan buat password pribadi melalui tautan berikut.')
            ->action('Buat Password', $url)
            ->line('Tautan ini berlaku selama 60 menit dan hanya dapat digunakan sekali.')
            ->line('Abaikan email ini jika Anda tidak merasa terkait dengan akun tersebut.');
    }
}
