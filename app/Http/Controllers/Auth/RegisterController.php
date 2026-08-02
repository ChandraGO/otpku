<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $request->merge([
            'whatsapp' => $this->normalizeWhatsApp((string) $request->input('whatsapp')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username'],
            'name' => ['required', 'string', 'max:100'],
            'whatsapp' => ['required', 'regex:/^62[0-9]{8,13}$/', 'unique:users,whatsapp'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'terms' => ['accepted'],
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp harus memakai format Indonesia, contoh 628123456789.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ], [
            'username' => 'username',
            'name' => 'nama',
            'whatsapp' => 'nomor WhatsApp',
            'email' => 'email',
            'password' => 'password',
            'terms' => 'persetujuan syarat penggunaan',
        ]);

        $user = DB::transaction(fn () => User::query()->create($data));

        Auth::login($user);
        $request->session()->regenerate();

        try {
            $otp->send($user);

            return redirect()
                ->route('verification.notice')
                ->with('success', 'Akun berhasil dibuat dan kode verifikasi berhasil dikirim ke email Anda.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('verification.notice')
                ->withErrors([
                    'otp' => 'Akun berhasil dibuat, tetapi kode verifikasi belum berhasil dikirim. Silakan tekan Kirim ulang kode atau hubungi dukungan.',
                ]);
        }
    }

    private function normalizeWhatsApp(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }
}
