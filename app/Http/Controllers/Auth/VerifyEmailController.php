<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) return redirect()->route('dashboard');
        return view('auth.verify-email');
    }
    public function verify(Request $request, EmailOtpService $service): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $service->verify($request->user()->email, $data['code'], 'verify_email');
        $request->user()->markEmailAsVerified();
        return redirect()->route('dashboard')->with('success', 'Email berhasil diverifikasi. Selamat datang!');
    }
    public function resend(Request $request, EmailOtpService $service): RedirectResponse
    {
        $service->send($request->user());
        return back()->with('success', 'Kode OTP baru telah dikirim.');
    }
}
