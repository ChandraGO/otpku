<?php

namespace App\Notifications;

use App\Services\MailSettingsConfigurator;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
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
        $isPasswordReset = $this->purpose === 'password_reset';
        $title = $isPasswordReset
            ? 'Kode reset password'
            : 'Verifikasi email Anda';
        $name = isset($notifiable->name)
            ? trim((string) $notifiable->name)
            : '';
        $websiteUrl = rtrim((string) config('app.url', url('/')), '/');
        $websiteDomain = parse_url($websiteUrl, PHP_URL_HOST) ?: $websiteUrl;

        return (new MailMessage)
            ->subject($title.' — '.config('app.name'))
            ->view('emails.otp', [
                'code' => $this->code,
                'minutes' => $this->minutes,
                'title' => $title,
                'recipientName' => $name,
                'isPasswordReset' => $isPasswordReset,
                'websiteUrl' => $websiteUrl,
                'websiteDomain' => $websiteDomain,
            ]);
    }
}
