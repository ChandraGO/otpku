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
    public function create(): View { return view('auth.register'); }
    public function store(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash:ascii', 'unique:users,username'],
            'name' => ['required', 'string', 'max:100'],
            'whatsapp' => ['required', 'regex:/^\+?[0-9]{9,18}$/', 'unique:users,whatsapp'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'terms' => ['accepted'],
        ]);
        $data['email'] = strtolower($data['email']);
        $data['whatsapp'] = ltrim($data['whatsapp'], '+');
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create($data);
            $user->rotateApiKey();
            return $user;
        });

        try {
            $otp->send($user);
        } catch (Throwable $exception) {
            // Jangan meninggalkan akun setengah jadi ketika SMTP gagal.
            $user->delete();

            throw $exception;
        }

        Auth::login($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();
        $request->session()->put('show_login_announcement', true);
        return redirect()->route('verification.notice')->with('success', 'Akun berhasil dibuat. Kode verifikasi telah dikirim ke email Anda.');
    }
}
