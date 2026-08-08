<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GitHubLoginController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = trim((string) config('services.github.client_id'));
        if ($clientId === '') {
            return redirect()->route('login')->withErrors([
                'github' => 'Masuk dengan GitHub belum dikonfigurasi oleh administrator.',
            ]);
        }

        $state = Str::random(48);
        $request->session()->put('oauth.github.state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'read:user user:email',
            'state' => $state,
            'allow_signup' => 'true',
        ]);

        return redirect()->away('https://github.com/login/oauth/authorize?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            $request->session()->forget('oauth.github.state');

            return redirect()->route('login')->withErrors([
                'github' => 'Masuk dengan GitHub dibatalkan atau ditolak. Silakan coba lagi.',
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:500'],
            'state' => ['required', 'string', 'max:200'],
        ]);

        $expectedState = (string) $request->session()->pull('oauth.github.state', '');
        if ($expectedState === '' || ! hash_equals($expectedState, (string) $data['state'])) {
            return redirect()->route('login')->withErrors([
                'github' => 'Sesi login GitHub tidak valid atau sudah kedaluwarsa. Silakan coba lagi.',
            ]);
        }

        try {
            $profile = $this->fetchProfile((string) $data['code']);
            $user = $this->resolveUser($profile);

            if (! $user->isActive()) {
                return redirect()->route('login')->withErrors([
                    'github' => 'Akun sedang dinonaktifkan.',
                ]);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            $isFirstLogin = blank($user->last_login_at);
            $user->ensureApiKey();
            $user->forceFill(['last_login_at' => now()])->save();
            if ($isFirstLogin) {
                $request->session()->put('show_login_announcement', true);
            }

            return redirect()->route('dashboard')->with(
                'success',
                'Masuk dengan GitHub berhasil. Selamat datang kembali, '.$user->name.'.',
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'github' => 'Masuk dengan GitHub gagal: '.$e->getMessage(),
            ]);
        }
    }

    private function fetchProfile(string $code): array
    {
        $clientId = trim((string) config('services.github.client_id'));
        $clientSecret = trim((string) config('services.github.client_secret'));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Konfigurasi GitHub OAuth belum lengkap.');
        }

        $tokenResponse = Http::acceptJson()
            ->asForm()
            ->timeout(20)
            ->connectTimeout(10)
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
            ]);

        if ($tokenResponse->failed()) {
            throw new RuntimeException('GitHub menolak pertukaran token OAuth.');
        }

        $token = trim((string) $tokenResponse->json('access_token'));
        if ($token === '') {
            throw new RuntimeException((string) ($tokenResponse->json('error_description') ?: 'Token akses GitHub tidak diterima.'));
        }

        $http = Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(20)
            ->connectTimeout(10);

        $userResponse = $http->get('https://api.github.com/user');
        if ($userResponse->failed()) {
            throw new RuntimeException('Profil GitHub tidak dapat dibaca.');
        }

        $github = $userResponse->json();
        if (! is_array($github) || ! filled($github['id'] ?? null)) {
            throw new RuntimeException('Respons profil GitHub tidak valid.');
        }

        $email = null;
        $emailsResponse = $http->get('https://api.github.com/user/emails');
        if ($emailsResponse->successful() && is_array($emailsResponse->json())) {
            $emails = collect($emailsResponse->json());
            $verifiedPrimary = $emails->first(fn ($row) => is_array($row)
                && ($row['primary'] ?? false)
                && ($row['verified'] ?? false)
                && filled($row['email'] ?? null));
            $verifiedAny = $emails->first(fn ($row) => is_array($row)
                && ($row['verified'] ?? false)
                && filled($row['email'] ?? null));
            $email = $verifiedPrimary['email'] ?? $verifiedAny['email'] ?? null;
        }

        if (! filled($email)) {
            throw new RuntimeException('Akun GitHub harus memiliki email terverifikasi agar dapat digunakan untuk login.');
        }

        return [
            'id' => (string) $github['id'],
            'login' => trim((string) ($github['login'] ?? 'github-user')),
            'name' => trim((string) ($github['name'] ?? $github['login'] ?? 'Pengguna GitHub')),
            'email' => strtolower(trim((string) $email)),
        ];
    }

    private function resolveUser(array $profile): User
    {
        $githubId = (string) $profile['id'];
        $email = (string) $profile['email'];

        $user = User::query()->where('github_id', $githubId)->first();
        if (! $user) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if ($user) {
            if (filled($user->github_id) && ! hash_equals((string) $user->github_id, $githubId)) {
                throw new RuntimeException('Email ini sudah terhubung ke akun GitHub lain.');
            }

            $user->forceFill([
                'github_id' => $githubId,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            return $user->refresh();
        }

        $username = $this->uniqueUsername((string) $profile['login']);

        return User::query()->create([
            'github_id' => $githubId,
            'username' => $username,
            'name' => (string) $profile['name'],
            'whatsapp' => $this->uniquePlaceholderWhatsApp($githubId),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Str::random(64),
            'role' => 'user',
            'status' => 'active',
            'balance' => 0,
            'theme' => 'dark',
        ]);
    }

    private function uniqueUsername(string $preferred): string
    {
        $base = Str::of($preferred)
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '-')
            ->trim('-._')
            ->limit(28, '')
            ->toString();

        $base = $base !== '' ? $base : 'github-user';
        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = Str::limit($base, 32 - strlen((string) $suffix), '').'-'.$suffix;
        }

        return $candidate;
    }

    private function uniquePlaceholderWhatsApp(string $githubId): string
    {
        $candidate = 'github-'.$githubId;
        if (! User::query()->where('whatsapp', $candidate)->exists()) {
            return $candidate;
        }

        return 'github-'.$githubId.'-'.Str::lower(Str::random(6));
    }

    private function redirectUri(): string
    {
        return (string) (config('services.github.redirect') ?: route('login.github.callback'));
    }
}
