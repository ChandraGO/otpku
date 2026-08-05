<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View { return view('auth.login'); }
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean']]);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $value = $field === 'email' ? strtolower($data['login']) : $data['login'];
        if (! Auth::attempt([$field => $value, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Email/username atau password tidak sesuai.'])->onlyInput('login');
        }
        $request->session()->regenerate();
        /** @var User $user */
        $user = $request->user();
        if (! $user->isActive()) {
            Auth::logout();
            return back()->withErrors(['login' => 'Akun sedang dinonaktifkan.']);
        }
        $user->ensureApiKey();
        $user->update(['last_login_at' => now()]);
        return redirect()
            ->intended($user->hasVerifiedEmail() ? route('dashboard') : route('verification.notice'))
            ->with('success', 'Login berhasil. Selamat datang kembali, '.$user->name.'.');
    }
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
