<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(): View { return view('auth.forgot-password'); }
    public function send(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($data['email']);
        $user = User::query()->where('email', $email)->first();
        if ($user) $otp->send($user, 'password_reset');
        return redirect()->route('password.reset.form', ['email' => $email])->with('success', 'Bila email terdaftar, kode reset telah dikirim.');
    }
    public function resetForm(Request $request): View { return view('auth.reset-password', ['email' => $request->query('email', old('email'))]); }
    public function reset(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'], 'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);
        $otp->verify(strtolower($data['email']), $data['code'], 'password_reset');
        User::query()->where('email', strtolower($data['email']))->firstOrFail()->update(['password' => $data['password']]);
        return redirect()->route('login')->with('success', 'Password berhasil diperbarui. Silakan login.');
    }
}
