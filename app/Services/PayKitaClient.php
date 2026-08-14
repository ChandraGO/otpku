<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PayKitaClient
{
    public function __construct(private readonly Settings $settings) {}

    public function createOrder(int $amount, string $reference, ?string $redirectUrl = null, ?string $webhookUrl = null, ?int $ttlSeconds = null): array
    {
        $payload = [
            'base_amount' => $amount,
            'reference' => $reference,
        ];

        if ($redirectUrl && str_starts_with(strtolower($redirectUrl), 'https://')) $payload['redirect_url'] = $redirectUrl;
        if ($webhookUrl && str_starts_with(strtolower($webhookUrl), 'https://')) $payload['webhook_url'] = $webhookUrl;
        if ($ttlSeconds) $payload['ttl_seconds'] = max(60, min(86400, $ttlSeconds));

        return $this->request('POST', '/api/orders', $payload, [
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }

    public function order(string $id): array
    {
        return $this->request('GET', '/api/orders/'.rawurlencode($id), meta: ['paykita_order_id' => $id]);
    }

    public function cancel(string $id): array
    {
        return $this->request('POST', '/api/orders/'.rawurlencode($id).'/cancel', meta: ['paykita_order_id' => $id]);
    }

    public function assertConfigured(): array
    {
        $key = $this->apiKey();
        if (! str_starts_with($key, 'pk_live_')) {
            throw new RuntimeException('API key PayKita harus menggunakan key LIVE project (pk_live_...).');
        }

        return [
            'base_url' => $this->baseUrl(),
            'api_key_configured' => true,
            'ttl_seconds' => $this->ttlSeconds(),
        ];
    }

    public function probe(): array
    {
        $this->assertConfigured();
        try {
            $this->order('pay_connection_test');
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404 && str_contains(strtolower($e->getMessage()), 'order_not_found')) {
                return ['ok' => true, 'message' => 'API key PayKita diterima.'];
            }
            throw $e;
        }

        return ['ok' => true, 'message' => 'Koneksi PayKita berhasil.'];
    }

    public function ttlSeconds(): int
    {
        return max(60, min(86400, (int) $this->settings->get('paykita.ttl_seconds', 600)));
    }

    private function apiKey(): string
    {
        $key = trim((string) $this->settings->get('paykita.api_key', config('services.paykita.api_key')));
        if ($key === '') throw new RuntimeException('API key PayKita belum dikonfigurasi.');
        return $key;
    }

    private function baseUrl(): string
    {
        $base = rtrim(trim((string) $this->settings->get('paykita.base_url', config('services.paykita.base_url', 'https://paykita.biz.id'))), '/');
        if ($base === '') $base = 'https://paykita.biz.id';
        if (str_ends_with(strtolower($base), '/api')) $base = substr($base, 0, -4);

        $parts = parse_url($base);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('URL dasar PayKita tidak valid. Gunakan https://paykita.biz.id.');
        }
        return $base;
    }

    private function request(string $method, string $endpoint, array $payload = [], array $meta = []): array
    {
        $started = hrtime(true);
        $status = null;
        $ok = false;
        $error = null;

        try {
            $http = Http::baseUrl($this->baseUrl())
                ->acceptJson()->asJson()
                ->withHeaders(['x-api-key' => $this->apiKey()])
                ->timeout(30)->connectTimeout(10);

            if (strtoupper($method) === 'GET') {
                $http = $http->retry(2, 400, throw: false);
            }

            $response = strtoupper($method) === 'GET'
                ? $http->get($endpoint, $payload)
                : $http->post($endpoint, $payload);

            $status = $response->status();
            $json = $response->json();
            if (! is_array($json)) $json = ['message' => trim($response->body())];

            if ($response->failed() || ($json['ok'] ?? true) === false) {
                $code = (string) data_get($json, 'error.code', 'paykita_error');
                $message = (string) data_get($json, 'error.message', $json['message'] ?? 'Permintaan PayKita gagal.');
                throw new RuntimeException('PayKita '.$code.': '.$message, $status ?: 500);
            }

            $ok = true;
            return $json;
        } catch (ConnectionException $e) {
            $error = 'PayKita tidak dapat dihubungi: '.$e->getMessage();
            throw new RuntimeException($error, previous: $e);
        } catch (Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            try {
                ApiLog::query()->create([
                    'user_id' => auth()->id(),
                    'provider' => 'paykita',
                    'method' => strtoupper($method),
                    'endpoint' => $endpoint,
                    'status_code' => $status,
                    'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                    'successful' => $ok,
                    'error_code' => $ok ? null : 'PAYKITA_ERROR',
                    'error_message' => $error ? str($error)->limit(1000)->toString() : null,
                    'request_meta' => $meta,
                ]);
            } catch (Throwable) {}
        }
    }
}
