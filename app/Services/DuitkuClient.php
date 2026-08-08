<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DuitkuClient
{
    public function __construct(private readonly Settings $settings) {}

    public function create(
        string $orderId,
        int $amount,
        string $paymentMethod,
        User $user,
        string $returnUrl,
        string $callbackUrl,
    ): array {
        $merchantCode = $this->merchantCode();
        $signature = $this->hmac($merchantCode.$orderId.$amount);
        $name = trim((string) ($user->name ?: 'Pelanggan KodeOTP'));
        $name = mb_substr($name, 0, 20);

        return $this->requestJson('POST', '/webapi/api/merchant/v2/inquiry', [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'merchantOrderId' => $orderId,
            'productDetails' => 'Isi saldo KodeOTP',
            'email' => (string) $user->email,
            'paymentMethod' => strtoupper($paymentMethod),
            'merchantUserInfo' => (string) $user->email,
            'customerVaName' => $name !== '' ? $name : 'Pelanggan',
            'returnUrl' => $returnUrl,
            'callbackUrl' => $callbackUrl,
            'signature' => $signature,
            'expiryPeriod' => $this->expiryMinutes(),
        ], [
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_method' => strtoupper($paymentMethod),
        ]);
    }

    public function paymentMethods(int $amount): array
    {
        $merchantCode = $this->merchantCode();
        $datetime = now()->format('Y-m-d H:i:s');

        $response = $this->requestJson('POST', '/webapi/api/merchant/paymentmethod/getpaymentmethod', [
            'merchantcode' => $merchantCode,
            'amount' => $amount,
            'datetime' => $datetime,
            'signature' => $this->hmac($merchantCode.$amount.$datetime),
        ], ['amount' => $amount]);

        if ((string) ($response['responseCode'] ?? '') !== '00') {
            throw new RuntimeException((string) ($response['responseMessage'] ?? 'Duitku gagal memuat metode pembayaran.'));
        }

        return is_array($response['paymentFee'] ?? null) ? $response['paymentFee'] : [];
    }

    public function transactionStatus(string $orderId): array
    {
        $merchantCode = $this->merchantCode();

        return $this->requestForm('POST', '/webapi/api/merchant/transactionStatus', [
            'merchantCode' => $merchantCode,
            'merchantOrderId' => $orderId,
            'signature' => $this->hmac($merchantCode.$orderId),
        ], ['order_id' => $orderId]);
    }

    public function verifyCallbackSignature(array $payload): bool
    {
        $merchantCode = (string) ($payload['merchantCode'] ?? '');
        $amount = (string) ($payload['amount'] ?? '');
        $orderId = (string) ($payload['merchantOrderId'] ?? '');
        $signature = strtolower(trim((string) ($payload['signature'] ?? '')));

        if ($merchantCode === '' || $amount === '' || $orderId === '' || $signature === '') {
            return false;
        }

        if (! hash_equals($this->merchantCode(), $merchantCode)) {
            return false;
        }

        return hash_equals($this->hmac($merchantCode.$amount.$orderId), $signature);
    }

    public function assertConfigured(): array
    {
        return [
            'environment' => $this->environment(),
            'base_url' => $this->baseUrl(),
            'merchant_code' => $this->merchantCode(),
            'api_key_configured' => $this->apiKey() !== '',
            'payment_method' => strtoupper((string) $this->settings->get('duitku.payment_method', 'NQ')),
            'expiry_minutes' => $this->expiryMinutes(),
        ];
    }

    public function merchantCode(): string
    {
        $value = trim((string) $this->settings->get('duitku.merchant_code', config('services.duitku.merchant_code')));
        if ($value === '') {
            throw new RuntimeException('Merchant Code Duitku belum dikonfigurasi.');
        }

        return $value;
    }

    public function expiryMinutes(): int
    {
        $configured = (int) $this->settings->get('duitku.expiry_minutes', 10);
        $method = strtoupper((string) $this->settings->get('duitku.payment_method', 'NQ'));

        // Dokumentasi Duitku membatasi sebagian QRIS (SP/GQ/SQ) maksimum
        // 60 menit, sedangkan NQ dan VA dapat memakai periode yang lebih panjang.
        $maximum = in_array($method, ['SP', 'GQ', 'SQ'], true) ? 60 : 1440;

        return max(5, min($maximum, $configured));
    }

    private function apiKey(): string
    {
        $value = trim((string) $this->settings->get('duitku.api_key', config('services.duitku.api_key')));
        if ($value === '') {
            throw new RuntimeException('API Key Duitku belum dikonfigurasi.');
        }

        return $value;
    }

    private function environment(): string
    {
        $value = strtolower((string) $this->settings->get('duitku.environment', config('services.duitku.environment', 'production')));

        return $value === 'sandbox' ? 'sandbox' : 'production';
    }

    private function baseUrl(): string
    {
        return $this->environment() === 'sandbox'
            ? 'https://sandbox.duitku.com'
            : 'https://passport.duitku.com';
    }

    private function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, $this->apiKey());
    }

    private function requestJson(string $method, string $endpoint, array $payload, array $meta = []): array
    {
        return $this->request($method, $endpoint, $payload, false, $meta);
    }

    private function requestForm(string $method, string $endpoint, array $payload, array $meta = []): array
    {
        return $this->request($method, $endpoint, $payload, true, $meta);
    }

    private function request(string $method, string $endpoint, array $payload, bool $asForm, array $meta): array
    {
        $started = hrtime(true);
        $status = null;
        $ok = false;
        $error = null;

        try {
            $http = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->timeout(30)
                ->connectTimeout(10)
                ->retry(2, 500, throw: false);
            $http = $asForm ? $http->asForm() : $http->asJson();

            $response = strtoupper($method) === 'GET'
                ? $http->get($endpoint, $payload)
                : $http->post($endpoint, $payload);

            $status = $response->status();
            $json = $response->json();
            if (! is_array($json)) {
                $json = ['message' => trim($response->body())];
            }

            if ($response->failed()) {
                $message = (string) (
                    $json['Message']
                    ?? $json['message']
                    ?? $json['responseMessage']
                    ?? $json['statusMessage']
                    ?? 'Permintaan Duitku gagal.'
                );

                throw new RuntimeException(
                    'Duitku HTTP '.$status.': '.($message !== '' ? $message : 'Respons tidak valid.'),
                    $status,
                );
            }

            $ok = true;
            return $json;
        } catch (ConnectionException $e) {
            $error = 'Duitku tidak dapat dihubungi: '.$e->getMessage();
            throw new RuntimeException($error, previous: $e);
        } catch (Throwable $e) {
            $error = $e->getMessage();
            throw $e;
        } finally {
            try {
                ApiLog::query()->create([
                    'user_id' => auth()->id(),
                    'provider' => 'duitku',
                    'method' => strtoupper($method),
                    'endpoint' => $endpoint,
                    'status_code' => $status,
                    'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                    'successful' => $ok,
                    'error_code' => $ok ? null : 'DUITKU_ERROR',
                    'error_message' => $error ? str($error)->limit(1000)->toString() : null,
                    'request_meta' => $meta,
                ]);
            } catch (Throwable) {
                // Pencatatan API tidak boleh menggagalkan pembayaran.
            }
        }
    }
}
