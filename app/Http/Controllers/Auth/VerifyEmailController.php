<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailOtpService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class VerifyEmailController extends Controller
{
    public function show(
        Request $request,
        EmailOtpService $service,
        Settings $settings,
    ): View|RedirectResponse {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', [
            'otpStatus' => $service->status($request->user()),
            'supportEmail' => (string) $settings->get('site.support_email', 'haficdh@gmail.com'),
            'supportWhatsapp' => preg_replace(
                '/\D+/',
                '',
                (string) $settings->get('site.support_whatsapp', '6282252509320'),
            ),
        ]);
    }

    public function verify(Request $request, EmailOtpService $service): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [], [
            'code' => 'kode verifikasi',
        ]);

        $service->verify($request->user()->email, $data['code'], 'verify_email');
        $request->user()->markEmailAsVerified();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Email berhasil diverifikasi. Selamat datang!');
    }

    public function resend(Request $request, EmailOtpService $service): RedirectResponse
    {
        try {
            $service->send($request->user());

            return back()->with(
                'success',
                'Kode verifikasi baru berhasil dikirim. Periksa kotak masuk atau folder spam.',
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'otp' => 'Kode verifikasi gagal dikirim. Silakan coba lagi atau hubungi dukungan.',
            ]);
        }
    }
}
