<?php
namespace App\Http\Controllers;

use App\Services\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View { return view('user.profile', ['user' => $request->user()]); }
    public function update(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username,'.$user->id],
            'whatsapp' => ['required', 'regex:/^\+?[0-9]{9,18}$/', 'unique:users,whatsapp,'.$user->id],
            'email' => ['required', 'email:rfc,dns', 'unique:users,email,'.$user->id],
            'theme' => ['required', 'in:dark,light'],
        ]);
        $emailChanged = strtolower($data['email']) !== strtolower($user->email);
        $data['email'] = strtolower($data['email']); $data['whatsapp'] = ltrim($data['whatsapp'], '+');
        if ($emailChanged) $data['email_verified_at'] = null;
        $user->update($data);
        if ($emailChanged) { $otp->send($user); return redirect()->route('verification.notice')->with('success', 'Profil diperbarui. Verifikasi email baru Anda.'); }
        return back()->with('success', 'Profil berhasil diperbarui.');
    }
    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);
        $request->user()->update(['password' => $data['password']]);
        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
