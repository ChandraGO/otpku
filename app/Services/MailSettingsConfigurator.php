<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSettingsConfigurator
{
    public function __construct(private readonly Settings $settings) {}

    public function configure(bool $purge = false): void
    {
        $mailer = (string) $this->settings->get('mail.mailer', config('mail.default', 'smtp'));
        $encryption = (string) $this->settings->get('mail.encryption', 'tls');
        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => $this->settings->get('mail.host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) $this->settings->get('mail.port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.username' => $this->settings->get('mail.username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password' => $this->settings->get('mail.password', config('mail.mailers.smtp.password')),
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : ($encryption === '' ? 'smtp' : null),
            'mail.from.address' => $this->settings->get('mail.from_address', config('mail.from.address')),
            'mail.from.name' => $this->settings->get('mail.from_name', config('mail.from.name')),
        ]);

        if ($purge) {
            try { Mail::purge($mailer); } catch (Throwable) {}
        }
    }
}
