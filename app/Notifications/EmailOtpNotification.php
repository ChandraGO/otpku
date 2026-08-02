<?php

namespace App\Notifications;

use App\Services\MailSettingsConfigurator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
        public readonly int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        app(MailSettingsConfigurator::class)->configure(purge: true);
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->purpose === 'password_reset'
            ? 'Kode reset password'
            : 'Verifikasi email Anda';
        $name = isset($notifiable->name) ? trim((string) $notifiable->name) : '';

        return (new MailMessage)
            ->subject($title.' — '.config('app.name'))
            ->greeting($name !== '' ? 'Halo, '.$name.'!' : 'Halo!')
            ->line($this->purpose === 'password_reset'
                ? 'Gunakan kode berikut untuk membuat password baru:'
                : 'Gunakan kode berikut untuk memverifikasi email akun Anda:')
            ->line('Kode OTP: '.$this->code)
            ->line("Kode berlaku selama {$this->minutes} menit dan hanya dapat digunakan satu kali.")
            ->line('Periksa kotak masuk dan folder spam apabila email belum terlihat.')
            ->line('Abaikan email ini apabila Anda tidak melakukan permintaan tersebut.');
    }
}
