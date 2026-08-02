<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SmsVirtualClient
{
    public function __construct(private readonly Settings $settings) {}

    public function balance(): array { return $this->request('GET', '/v1/public/balance'); }
    public function balanceHistory(array $query = []): array { return $this->request('GET', '/v1/public/balance/history', query: $query); }
    public function profile(): array { return $this->request('GET', '/v1/public/profile'); }

    public function depositRate(array $query = []): array { return $this->request('GET', '/v1/public/deposits/rate', query: $query); }
    public function depositHistory(array $query = []): array { return $this->request('GET', '/v1/public/deposits/history', query: $query); }
    public function depositMethods(array $query = []): array { return $this->request('GET', '/v1/public/deposits', query: $query); }
    public function requestDeposit(array $payload, ?string $idempotencyKey = null): array { return $this->request('POST', '/v1/public/deposits/request', $payload, headers: $this->idempotencyHeader($idempotencyKey)); }
    public function cancelDeposit(string $id): array { return $this->request('PUT', "/v1/public/deposits/cancel/{$id}"); }

    public function countries(array $query = []): array { return $this->request('GET', '/v1/public/countries', query: $query, auth: false); }
    public function operators(array $query = []): array { return $this->request('GET', '/v1/public/operators', query: $query, auth: false); }
    public function services(array $query = []): array { return $this->request('GET', '/v1/public/services', query: $query, auth: false); }
    public function servicesByCountry(string $countryId, array $query = []): array { return $this->request('GET', '/v1/public/services/list', query: ['countryId' => $countryId, ...$query], auth: false); }

    public function orderHistory(array $query = []): array { return $this->request('GET', '/v1/public/orders/history', query: $query); }
    public function activationHistory(array $query = []): array { return $this->request('GET', '/v1/public/orders/history-activation', query: $query); }
    public function ongoingActivations(array $query = []): array { return $this->request('GET', '/v1/public/orders/ongoing-activation', query: $query); }
    public function requestSingleService(array $payload, string $idempotencyKey): array { return $this->request('POST', '/v1/public/orders/request-single-service', $payload, headers: $this->idempotencyHeader($idempotencyKey)); }
    public function getStatus(string $activationId): array { return $this->request('GET', "/v1/public/orders/getStatus/{$activationId}"); }
    public function ready(string $activationId): array { return $this->request('PUT', "/v1/public/orders/ready/{$activationId}"); }
    public function resend(string $activationId): array { return $this->request('PUT', "/v1/public/orders/resend/{$activationId}"); }
    public function cancel(string $activationId): array { return $this->request('PUT', "/v1/public/orders/cancel/{$activationId}"); }
    public function complete(string $activationId): array { return $this->request('PUT', "/v1/public/orders/complete/{$activationId}"); }

    // Compatibility methods documented in the provider's end-to-end partner flow.
    public function requestMultiService(array $payload, string $idempotencyKey): array { return $this->request('POST', '/v1/orders/request-multi-service', $payload, headers: $this->idempotencyHeader($idempotencyKey)); }
    public function reactivate(string $activationId): array { return $this->request('POST', "/v1/orders/reactivate/{$activationId}"); }
    public function serviceCountries(string $serviceId, array $query = []): array { return $this->request('GET', "/v1/services/{$serviceId}/countries", query: $query, auth: false); }

    public function payload(array $response): mixed
    {
        return $response['data'] ?? $response;
    }

    public function rows(array $response, array $keys = []): array
    {
        $payload = $this->payload($response);
        if (is_array($payload) && array_is_list($payload)) return $payload;
        foreach ([...$keys, 'items', 'results', 'rows', 'countries', 'services', 'operators', 'activations', 'orders', 'deposits'] as $key) {
            $candidate = is_array($payload) ? ($payload[$key] ?? null) : null;
            if (is_array($candidate)) return array_is_list($candidate) ? $candidate : array_values($candidate);
        }
        return [];
    }

    private function request(string $method, string $endpoint, array $payload = [], array $query = [], array $headers = [], bool $auth = true): array
    {
        $started = hrtime(true);
        $statusCode = null;
        $successful = false;
        $errorCode = null;
        $errorMessage = null;

        try {
            $request = $this->http($auth)->withHeaders($headers);
            $response = match (strtoupper($method)) {
                'GET' => $request->get($endpoint, $query),
                'POST' => $request->post($endpoint, $payload),
                'PUT' => $request->put($endpoint, $payload),
                'DELETE' => $request->delete($endpoint, $payload),
                default => throw new RuntimeException("HTTP method {$method} tidak didukung."),
            };
            $statusCode = $response->status();
            $json = $response->json();
            if (! is_array($json)) $json = ['message' => $response->body()];

            if ($response->failed()) {
                $errorCode = (string) ($json['error'] ?? $json['code'] ?? 'PROVIDER_ERROR');
                $errorMessage = (string) ($json['message'] ?? $json['error'] ?? 'Permintaan ke SMS Virtual gagal.');
                throw new RuntimeException($errorMessage, $statusCode);
            }
            $successful = true;
            return $json;
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            throw new RuntimeException('SMS Virtual tidak dapat dihubungi.', previous: $e);
        } finally {
            $this->log($method, $endpoint, $statusCode, $started, $successful, $errorCode, $errorMessage, $query);
        }
    }

    private function http(bool $auth): PendingRequest
    {
        $baseUrl = rtrim((string) $this->settings->get('sms_virtual.base_url', config('services.sms_virtual.base_url')), '/');
        $timeout = max(5, (int) $this->settings->get('sms_virtual.timeout', config('services.sms_virtual.timeout', 30)));
        $request = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout(10)
            ->retry(2, 500, throw: false);

        if ($auth) {
            $apiKey = (string) $this->settings->get('sms_virtual.api_key', config('services.sms_virtual.api_key'));
            if ($apiKey === '') throw new RuntimeException('API key SMS Virtual belum dikonfigurasi di admin.');
            $request = $request->withHeader('x-api-key', $apiKey);
        }
        return $request;
    }

    private function idempotencyHeader(?string $key): array
    {
        return filled($key) ? ['Idempotency-Key' => $key] : [];
    }

    private function log(string $method, string $endpoint, ?int $statusCode, int $started, bool $successful, ?string $errorCode, ?string $errorMessage, array $query): void
    {
        try {
            ApiLog::query()->create([
                'user_id' => auth()->id(),
                'provider' => 'sms_virtual',
                'method' => strtoupper($method),
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'successful' => $successful,
                'error_code' => $errorCode,
                'error_message' => $errorMessage ? str($errorMessage)->limit(1000)->toString() : null,
                'request_meta' => $query ? Arr::except($query, ['api_key', 'token']) : null,
            ]);
        } catch (Throwable) {
            // Logging must never break a provider request.
        }
    }
}
