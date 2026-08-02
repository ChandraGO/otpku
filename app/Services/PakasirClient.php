<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Support\Settings;
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
            'project' => $this->project(), 'order_id' => $orderId, 'amount' => $amount, 'api_key' => $this->apiKey(),
        ]);
    }

    public function detail(string $orderId, int $amount): array
    {
        return $this->request('GET', '/api/transactiondetail', query: [
            'project' => $this->project(), 'order_id' => $orderId, 'amount' => $amount, 'api_key' => $this->apiKey(),
        ]);
    }

    public function cancel(string $orderId, int $amount): array
    {
        return $this->request('POST', '/api/transactioncancel', [
            'project' => $this->project(), 'order_id' => $orderId, 'amount' => $amount, 'api_key' => $this->apiKey(),
        ]);
    }

    public function simulate(string $orderId, int $amount): array
    {
        return $this->request('POST', '/api/paymentsimulation', [
            'project' => $this->project(), 'order_id' => $orderId, 'amount' => $amount, 'api_key' => $this->apiKey(),
        ]);
    }

    public function checkoutUrl(string $orderId, int $amount): string
    {
        $base = rtrim((string) $this->settings->get('pakasir.base_url', config('services.pakasir.base_url')), '/');
        return $base.'/pay/'.rawurlencode($this->project()).'/'.$amount.'?'.http_build_query([
            'order_id' => $orderId,
            'redirect' => route('topups.index'),
        ]);
    }

    public function project(): string
    {
        $project = (string) $this->settings->get('pakasir.project', config('services.pakasir.project'));
        if ($project === '') throw new RuntimeException('Project slug Pakasir belum dikonfigurasi.');
        return $project;
    }

    private function apiKey(): string
    {
        $key = (string) $this->settings->get('pakasir.api_key', config('services.pakasir.api_key'));
        if ($key === '') throw new RuntimeException('API key Pakasir belum dikonfigurasi.');
        return $key;
    }

    private function request(string $method, string $endpoint, array $payload = [], array $query = []): array
    {
        $started = hrtime(true); $status = null; $ok = false; $error = null;
        try {
            $http = Http::baseUrl(rtrim((string) $this->settings->get('pakasir.base_url', config('services.pakasir.base_url')), '/'))
                ->acceptJson()->asJson()->timeout(30)->connectTimeout(10)->retry(2, 500, throw: false);
            $response = strtoupper($method) === 'GET' ? $http->get($endpoint, $query) : $http->post($endpoint, $payload);
            $status = $response->status();
            $json = $response->json();
            if (! is_array($json)) $json = ['message' => $response->body()];
            if ($response->failed()) throw new RuntimeException((string) ($json['message'] ?? $json['error'] ?? 'Permintaan Pakasir gagal.'));
            $ok = true;
            return $json;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            try {
                ApiLog::query()->create([
                    'user_id' => auth()->id(), 'provider' => 'pakasir', 'method' => strtoupper($method), 'endpoint' => $endpoint,
                    'status_code' => $status, 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000), 'successful' => $ok,
                    'error_code' => $ok ? null : 'PAKASIR_ERROR', 'error_message' => $error ? str($error)->limit(1000)->toString() : null,
                    'request_meta' => ['order_id' => $payload['order_id'] ?? $query['order_id'] ?? null, 'amount' => $payload['amount'] ?? $query['amount'] ?? null],
                ]);
            } catch (Throwable) {}
        }
    }
}
