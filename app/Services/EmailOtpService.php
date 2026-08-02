<?php

namespace App\Services;

use App\Models\EmailOtp;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailOtpService
{
    public function __construct(private readonly Settings $settings) {}

    public function expiryMinutes(): int
    {
        return max(3, (int) $this->settings->get('auth.email_otp_expiry_minutes', 10));
    }

    public function resendSeconds(): int
    {
        return max(30, (int) $this->settings->get('auth.email_otp_resend_seconds', 60));
    }

    public function status(User $user, string $purpose = 'verify_email'): array
    {
        $latest = EmailOtp::query()
            ->where('email', strtolower($user->email))
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        $remaining = 0;
        if ($latest) {
            $remaining = max(
                0,
                now()->diffInSeconds(
                    $latest->created_at->copy()->addSeconds($this->resendSeconds()),
                    false,
                ),
            );
        }

        return [
            'expiry_minutes' => $this->expiryMinutes(),
            'resend_seconds' => $this->resendSeconds(),
            'resend_remaining' => $remaining,
            'expires_at' => $latest?->expires_at?->getTimestamp(),
            'has_active_code' => (bool) ($latest?->isUsable()),
        ];
    }

    public function send(User $user, string $purpose = 'verify_email'): EmailOtp
    {
        $email = strtolower($user->email);
        $minutes = $this->expiryMinutes();
        $resendSeconds = $this->resendSeconds();

        $last = EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if ($last && $last->created_at->gt(now()->subSeconds($resendSeconds))) {
            $remaining = max(
                1,
                now()->diffInSeconds(
                    $last->created_at->copy()->addSeconds($resendSeconds),
                    false,
                ),
            );

            throw ValidationException::withMessages([
                'otp' => 'Kode baru dapat diminta kembali dalam '.$remaining.' detik.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $otp = EmailOtp::query()->create([
            'user_id' => $user->id,
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($minutes),
            'ip_address' => request()?->ip(),
        ]);

        try {
            // Pengiriman langsung diperlukan agar antarmuka hanya menampilkan
            // sukses setelah SMTP benar-benar menerima pesan tanpa exception.
            $user->notifyNow(new EmailOtpNotification($code, $purpose, $minutes));
        } catch (Throwable $exception) {
            $otp->delete();
            Log::error('Pengiriman OTP email gagal.', [
                'user_id' => $user->id,
                'email' => $email,
                'purpose' => $purpose,
                'exception' => $exception,
            ]);
            throw $exception;
        }

        EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('id', '<>', $otp->id)
            ->update(['used_at' => now()]);

        Log::info('OTP email berhasil dikirim.', [
            'user_id' => $user->id,
            'email' => $email,
            'purpose' => $purpose,
            'otp_id' => $otp->id,
            'expires_at' => $otp->expires_at?->toIso8601String(),
        ]);

        return $otp->refresh();
    }

    public function verify(string $email, string $code, string $purpose): EmailOtp
    {
        $result = DB::transaction(function () use ($email, $code, $purpose): EmailOtp|string {
            $otp = EmailOtp::query()
                ->where('email', strtolower($email))
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || ! $otp->isUsable()) return 'invalid';

            $otp->increment('attempts');
            if (! Hash::check($code, $otp->code_hash)) return 'mismatch';

            $otp->update(['used_at' => now()]);
            return $otp->refresh();
        }, 3);

        if ($result === 'invalid') {
            throw ValidationException::withMessages(['code' => 'Kode OTP tidak valid atau sudah kedaluwarsa.']);
        }
        if ($result === 'mismatch') {
            throw ValidationException::withMessages(['code' => 'Kode OTP tidak sesuai.']);
        }
        return $result;
    }
}
