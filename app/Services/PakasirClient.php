<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PakasirClient
{
    public function __construct(private readonly Settings $settings) {}

    public function create(string $orderId, int $amount, ?string $method = null): array
    {
        $method ??= (string) $this->settings->get('pakasir.payment_method', 'qris');

        return $this->request('POST', "/api/transactioncreate/{$method}", [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $amount,
            'api_key' => $this->apiKey(),
        ]);
    }

    public function detail(string $orderId, int $amount): array
    {
        return $this->request('GET', '/api/transactiondetail', query: [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $amount,
            'api_key' => $this->apiKey(),
        ]);
    }

    public function cancel(string $orderId, int $amount): array
    {
        return $this->request('POST', '/api/transactioncancel', [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $amount,
            'api_key' => $this->apiKey(),
        ]);
    }

    public function simulate(string $orderId, int $amount): array
    {
        return $this->request('POST', '/api/paymentsimulation', [
            'project' => $this->project(),
            'order_id' => $orderId,
            'amount' => $amount,
            'api_key' => $this->apiKey(),
        ]);
    }

    public function checkoutUrl(
        string $orderId,
        int $amount,
        ?string $redirect = null,
        ?string $method = null,
    ): string {
        $query = ['order_id' => $orderId];

        if (filled($redirect)) {
            $query['redirect'] = $redirect;
        }

        if (($method ?? '') === 'qris') {
            $query['qris_only'] = 1;
        }

        return $this->baseUrl().'/pay/'.rawurlencode($this->project()).'/'.$amount.'?'.http_build_query($query);
    }

    public function project(): string
    {
        $project = trim((string) $this->settings->get(
            'pakasir.project',
            config('services.pakasir.project'),
        ));

        if ($project === '') {
            throw new RuntimeException('Project slug Pakasir belum dikonfigurasi.');
        }

        return $project;
    }

    public function assertConfigured(): array
    {
        return [
            'base_url' => $this->baseUrl(),
            'project' => $this->project(),
            'api_key_configured' => $this->apiKey() !== '',
        ];
    }

    private function apiKey(): string
    {
        $key = trim((string) $this->settings->get(
            'pakasir.api_key',
            config('services.pakasir.api_key'),
        ));

        if ($key === '') {
            throw new RuntimeException('API key Pakasir belum dikonfigurasi.');
        }

        return $key;
    }

    private function baseUrl(): string
    {
        $base = trim((string) $this->settings->get(
            'pakasir.base_url',
            config('services.pakasir.base_url', 'https://app.pakasir.com'),
        ));

        if ($base === '') {
            throw new RuntimeException('Base URL Pakasir belum dikonfigurasi.');
        }

        $base = rtrim($base, '/');

        // Admin sometimes pastes the API endpoint instead of the host. The
        // checkout URL and API client both require the host without /api.
        if (str_ends_with(strtolower($base), '/api')) {
            $base = substr($base, 0, -4);
        }

        $parts = parse_url($base);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('Base URL Pakasir tidak valid. Gunakan https://app.pakasir.com.');
        }

        // Canonical host from Pakasir integration documentation.
        if (in_array(strtolower((string) $parts['host']), ['pakasir.com', 'www.pakasir.com'], true)) {
            $base = 'https://app.pakasir.com';
        }

        return rtrim($base, '/');
    }

    private function request(
        string $method,
        string $endpoint,
        array $payload = [],
        array $query = [],
    ): array {
        $started = hrtime(true);
        $status = null;
        $ok = false;
        $error = null;

        try {
            $http = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->connectTimeout(10)
                ->retry(2, 500, throw: false);

            $response = strtoupper($method) === 'GET'
                ? $http->get($endpoint, $query)
                : $http->post($endpoint, $payload);

            $status = $response->status();
            $json = $response->json();

            if (! is_array($json)) {
                $json = ['message' => trim($response->body())];
            }

            if ($response->failed()) {
                $message = (string) (
                    $json['message']
                    ?? $json['error']
                    ?? data_get($json, 'errors.0.message')
                    ?? 'Permintaan Pakasir gagal.'
                );

                throw new RuntimeException(
                    'Pakasir HTTP '.$status.': '.($message !== '' ? $message : 'Respons tidak valid.'),
                    $status,
                );
            }

            $ok = true;

            return $json;
        } catch (ConnectionException $exception) {
            $error = 'Pakasir tidak dapat dihubungi: '.$exception->getMessage();

            throw new RuntimeException($error, previous: $exception);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            throw $exception;
        } finally {
            try {
                ApiLog::query()->create([
                    'user_id' => auth()->id(),
                    'provider' => 'pakasir',
                    'method' => strtoupper($method),
                    'endpoint' => $endpoint,
                    'status_code' => $status,
                    'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                    'successful' => $ok,
                    'error_code' => $ok ? null : 'PAKASIR_ERROR',
                    'error_message' => $error
                        ? str($error)->limit(1000)->toString()
                        : null,
                    'request_meta' => [
                        'order_id' => $payload['order_id'] ?? $query['order_id'] ?? null,
                        'amount' => $payload['amount'] ?? $query['amount'] ?? null,
                    ],
                ]);
            } catch (Throwable) {
                // Logging must never turn a payment request into a 500 error.
            }
        }
    }
}
