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

        $user = User::query()
            ->where('api_key_hash', hash('sha256', $plain))
            ->first();

        if (! $user || ! hash_equals((string) $user->api_key_hash, hash('sha256', $plain))) {
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

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => ['code' => 'UNAUTHENTICATED'],
        ], 401);
    }
}
