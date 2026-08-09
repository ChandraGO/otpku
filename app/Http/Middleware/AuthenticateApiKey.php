<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = trim((string) $request->header('x-api-key'));

        if ($plain === '') {
            return $this->unauthorized('Header x-api-key wajib dikirim.');
        }

        $candidates = array_values(array_unique(array_filter([
            $plain,
            $this->withNewPrefix($plain),
            $this->withLegacyPrefix($plain),
        ])));

        $hashes = array_map(fn (string $value): string => hash('sha256', $value), $candidates);

        $user = User::query()
            ->whereIn('api_key_hash', $hashes)
            ->first();

        if (! $user || ! in_array((string) $user->api_key_hash, $hashes, true)) {
            return $this->unauthorized('API key tidak valid.');
        }

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif.',
                'error' => ['code' => 'ACCOUNT_INACTIVE'],
            ], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function withNewPrefix(string $plain): ?string
    {
        if (! str_starts_with($plain, 'otp_live_')) {
            return null;
        }

        return 'dapetotp_'.substr($plain, strlen('otp_live_'));
    }

    private function withLegacyPrefix(string $plain): ?string
    {
        if (! str_starts_with($plain, 'dapetotp_')) {
            return null;
        }

        return 'otp_live_'.substr($plain, strlen('dapetotp_'));
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => ['code' => 'UNAUTHENTICATED'],
        ], 401);
    }
}
