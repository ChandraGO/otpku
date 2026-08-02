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

    public function send(User $user, string $purpose = 'verify_email'): void
    {
        $minutes = max(3, (int) $this->settings->get('auth.email_otp_expiry_minutes', 10));
        $resendSeconds = max(30, (int) $this->settings->get('auth.email_otp_resend_seconds', 60));
        $email = strtolower(trim((string) $user->email));
        $last = EmailOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($last && $last->created_at->gt(now()->subSeconds($resendSeconds))) {
            throw ValidationException::withMessages([
                'otp' => 'Kode baru dapat diminta kembali setelah '.$resendSeconds.' detik.',
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
            // OTP autentikasi harus sampai saat request berlangsung. Ini sengaja
            // tidak bergantung pada queue worker agar daftar/forgot password tetap
            // berfungsi meskipun worker sedang restart atau tertinggal.
            $user->notifyNow(new EmailOtpNotification($code, $purpose, $minutes));
        } catch (Throwable $exception) {
            $otp->delete();

            Log::error('Gagal mengirim email OTP.', [
                'user_id' => $user->id,
                'email' => $email,
                'purpose' => $purpose,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Kode OTP belum berhasil dikirim. Silakan coba kembali atau hubungi admin.',
            ]);
        }

        try {
            EmailOtp::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNull('used_at')
                ->where('id', '<>', $otp->id)
                ->update(['used_at' => now()]);
        } catch (Throwable $exception) {
            // Kode terbaru tetap valid dan selalu dipilih saat verifikasi.
            Log::warning('OTP lama gagal dinonaktifkan setelah email terkirim.', [
                'email' => $email,
                'purpose' => $purpose,
                'otp_id' => $otp->id,
                'message' => $exception->getMessage(),
            ]);
        }
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

            if (! $otp || ! $otp->isUsable()) {
                return 'invalid';
            }

            $otp->increment('attempts');

            if (! Hash::check($code, $otp->code_hash)) {
                return 'mismatch';
            }

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
