<?php

namespace App\Services;

use App\Models\EmailOtp;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    public function __construct(private readonly Settings $settings) {}

    public function send(User $user, string $purpose = 'verify_email'): void
    {
        $minutes = max(3, (int) $this->settings->get('auth.email_otp_expiry_minutes', 10));
        $resendSeconds = max(30, (int) $this->settings->get('auth.email_otp_resend_seconds', 60));
        $last = EmailOtp::query()->where('email', $user->email)->where('purpose', $purpose)->latest()->first();
        if ($last && $last->created_at->gt(now()->subSeconds($resendSeconds))) {
            throw ValidationException::withMessages(['otp' => 'Kode baru dapat diminta kembali setelah '.$resendSeconds.' detik.']);
        }
        EmailOtp::query()->where('email', $user->email)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);
        $code = (string) random_int(100000, 999999);
        EmailOtp::query()->create([
            'user_id' => $user->id, 'email' => $user->email, 'purpose' => $purpose, 'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($minutes), 'ip_address' => request()?->ip(),
        ]);
        $user->notify(new EmailOtpNotification($code, $purpose, $minutes));
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
