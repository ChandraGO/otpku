<?php

namespace App\Http\Controllers;

use App\Models\SmsCountry;
use App\Services\EmailOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function apiDocs(Request $request): View
    {
        $request->user()->ensureApiKey();

        return view('user.api-docs');
    }

    public function edit(Request $request): View
    {
        $user = $request->user();
        $user->ensureApiKey();

        return view('user.profile', [
            'user' => $user->fresh('defaultCountry'),
            'countries' => SmsCountry::query()
                ->select(['id', 'name', 'iso_code', 'flag_url'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username,'.$user->id],
            'whatsapp' => ['required', 'regex:/^\+?[0-9]{9,18}$/', 'unique:users,whatsapp,'.$user->id],
            'telegram_id' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'unique:users,email,'.$user->id],
            'default_country_id' => ['nullable', 'integer', 'exists:sms_countries,id'],
            'theme' => ['required', 'in:dark,light'],
        ]);

        $emailChanged = strtolower($data['email']) !== strtolower($user->email);
        $data['email'] = strtolower($data['email']);
        $data['whatsapp'] = filled($data['whatsapp'] ?? null) ? ltrim($data['whatsapp'], '+') : null;
        $data['telegram_id'] = filled($data['telegram_id'] ?? null) ? trim($data['telegram_id']) : null;
        if ($emailChanged) $data['email_verified_at'] = null;

        $user->update($data);

        if ($emailChanged) {
            $otp->send($user);
            return redirect()->route('verification.notice')->with('success', 'Profil diperbarui. Silakan verifikasi alamat email baru Anda.');
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function theme(Request $request): array
    {
        $data = $request->validate(['theme' => ['required', 'in:dark,light']]);
        $request->user()->update(['theme' => $data['theme']]);

        return ['ok' => true, 'theme' => $data['theme']];
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password berhasil diperbarui. Semua login berikutnya harus memakai password baru.');
    }

    public function rotateApiKey(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $plain = $request->user()->rotateApiKey();

        return back()
            ->with('success', 'API key baru berhasil dibuat. Integrasi yang masih memakai key lama akan menerima respons 401.')
            ->with('new_api_key', $plain);
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->deletion_request_status === 'pending') {
            return back()->withErrors(['deletion' => 'Permintaan penghapusan akun Anda masih menunggu peninjauan admin.']);
        }

        $user->update([
            'deletion_requested_at' => now(),
            'deletion_request_reason' => $data['reason'],
            'deletion_request_status' => 'pending',
            'deletion_reviewed_at' => null,
        ]);

        return back()->with('success', 'Permintaan penghapusan akun telah dikirim dan akan ditinjau admin. Akun tetap aktif sampai permintaan disetujui.');
    }
}
