<?php

namespace App\Jobs;

use App\Models\OtpOrder;
use App\Services\OtpOrderStatusService;
use App\Services\SmsVirtualClient;
use App\Services\WalletService;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PlaceOtpOrder implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 5;
    public int $uniqueFor = 300;
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public readonly string $orderId) {}

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    public function handle(SmsVirtualClient $client, OtpOrderStatusService $statusService, Settings $settings, WalletService $wallet): void
    {
        $order = OtpOrder::query()->with('user')->find($this->orderId);
        if (! $order || $order->provider_activation_id || $order->refunded_at || in_array($order->status, ['completed', 'cancelled', 'refunded'], true)) return;
        if (($order->payment_status ?? 'paid') !== 'paid') return;

        if (! $order->provider_price_id) {
            $this->terminalFailure($order, 'Referensi harga provider tidak tersedia.', $wallet);
            return;
        }

        try {
            $response = $client->requestSingleService(array_filter([
                'serviceCountryPriceId' => $order->provider_price_id,
                'operatorId' => $order->provider_operator_id,
                'quantity' => 1,
                // Keep the provider charge tied to the exact price shown in
                // KodeOTP. When autoSearchServer=true SMS Virtual may fall back
                // to another server/priceId with a higher sellPrice, which can
                // make a Rp1.000 quote consume Rp2.439 at the provider.
                'autoSearchServer' => false,
            ], fn ($value) => $value !== null && $value !== ''), $order->idempotency_key);

            $provider = $response['data'] ?? $response;
            if (! is_array($provider)) $provider = [];

            // SMS Virtual menggunakan beberapa identifier berbeda:
            // - order.id / orderId  : UUID order
            // - activationId        : ID numerik eksternal
            // - activation record id: UUID order-detail yang WAJIB dipakai
            //   oleh getStatus/ready/resend/cancel/complete/reactivate.
            $providerOrderId = $this->findValue($provider, [
                'orderId',
                'order.id',
                'activations.0.orderId',
                'items.0.orderId',
                '0.orderId',
            ]);

            $numericActivationId = $this->findValue($provider, [
                'activationId',
                'activation.activationId',
                'activations.0.activationId',
                'items.0.activationId',
                '0.activationId',
                'order.activationId',
            ]);

            // Bentuk create-order production yang sudah terkonfirmasi hanya
            // memberi data.id=<UUID order>. Jangan perlakukan sebagai UUID
            // action kecuali payload jelas merupakan activation record.
            if (! $providerOrderId && ! $numericActivationId) {
                $providerOrderId = $this->findValue($provider, ['id']);
            }

            $resolvedActivation = null;
            $providerActionId = null;

            // Jika response memang activation record (ada orderId + activationId),
            // field id adalah UUID order-detail yang dicari endpoint action.
            if ($providerOrderId && $numericActivationId) {
                $candidate = $this->findValue($provider, ['id', 'activation.id', 'activations.0.id', 'items.0.id', '0.id']);
                if (is_string($candidate) && Str::isUuid($candidate)) {
                    $providerActionId = $candidate;
                    $resolvedActivation = $provider;
                }
            }

            if ($providerOrderId && ! $providerActionId) {
                $resolvedActivation = $this->findActivationByOrderId($client, (string) $providerOrderId);
                $providerActionId = $this->findValue($resolvedActivation ?? [], ['id']);
                $numericActivationId = $this->findValue($resolvedActivation ?? [], ['activationId']) ?: $numericActivationId;
            }

            if (! $providerOrderId && $resolvedActivation) {
                $providerOrderId = $this->findValue($resolvedActivation, ['orderId', 'order.id']);
            }

            if (! is_string($providerActionId) || ! Str::isUuid($providerActionId)) {
                throw new RuntimeException('Provider sudah membuat order, tetapi UUID activation detail belum tersedia. Akan dicoba lagi otomatis.', 503);
            }

            $activationPayload = $resolvedActivation ?: $provider;
            $phoneNumber = $this->findValue($activationPayload, [
                'phoneNumber',
                'number',
                'phone',
                'activation.phoneNumber',
                'activation.number',
            ]);
            $expiresAt = $this->providerDate($this->findValue($activationPayload, [
                'expiredTime',
                'expiredAt',
                'expiresAt',
                'activation.expiredTime',
                'activation.expiredAt',
                'activation.expiresAt',
                'activations.0.expiredTime',
                'activations.0.expiredAt',
                'activations.0.expiresAt',
                '0.expiredTime',
                '0.expiredAt',
                '0.expiresAt',
            ]))
                ?: now()->addMinutes((int) $settings->get('orders.default_expiry_minutes', 20));

            $cancelProviderActivation = false;

            DB::transaction(function () use ($order, $response, $providerActionId, $providerOrderId, $phoneNumber, $expiresAt, &$cancelProviderActivation): void {
                $locked = OtpOrder::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->provider_activation_id) return;

                // User dapat membatalkan ketika request ke provider masih in-flight.
                // Jangan menghidupkan lagi order lokal yang sudah dibatalkan/refund.
                if ($locked->refunded_at || in_array($locked->status, ['cancelled', 'refunded'], true)) {
                    $cancelProviderActivation = true;
                    return;
                }

                $updates = [
                    'provider_activation_id' => (string) $providerActionId,
                    'provider_order_id' => $providerOrderId ? (string) $providerOrderId : null,
                    'provider_payload' => $response,
                    'provider_message' => null,
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                ];
                if ($phoneNumber) $updates['phone_number'] = (string) $phoneNumber;
                $locked->update($updates);
            }, 3);

            if ($cancelProviderActivation) {
                try {
                    $client->cancel((string) $providerActionId);
                } catch (Throwable $cancelError) {
                    report($cancelError);
                }
                return;
            }

            $fresh = $order->refresh();
            if ($fresh->provider_activation_id) {
                // Jika activation ditemukan lewat ongoing/history, gunakan record
                // activation tersebut untuk langsung mengisi nomor/status/durasi.
                // provider_activation_id sengaja menyimpan UUID order-detail
                // karena itulah identifier yang diterima endpoint provider.
                $statusService->apply(
                    $fresh,
                    $resolvedActivation ? ['data' => $resolvedActivation] : $response,
                );
            }
        } catch (Throwable $e) {
            $code = (int) $e->getCode();
            if ($code >= 400 && $code < 500 && ! in_array($code, [408, 409, 425, 429], true)) {
                $this->terminalFailure($order, $e->getMessage(), $wallet);
                return;
            }
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $order = OtpOrder::query()->find($this->orderId);
        if (! $order || $order->provider_activation_id || $order->refunded_at) return;
        $order->update([
            'status' => 'provider_pending',
            'provider_message' => str($exception->getMessage())->limit(1000)->toString(),
        ]);
    }

    private function terminalFailure(OtpOrder $order, string $message, WalletService $wallet): void
    {
        DB::transaction(function () use ($order, $message, $wallet): void {
            $locked = OtpOrder::query()->with('user')->lockForUpdate()->findOrFail($order->id);
            if ($locked->provider_activation_id || $locked->refunded_at) return;
            $refund = $wallet->refundOrderPayment(
                $locked,
                'Refund pesanan gagal '.$locked->service_name,
                ['reason' => str($message)->limit(500)->toString()],
            );
            $locked->update([
                'status' => 'failed',
                'provider_message' => str($message)->limit(1000)->toString(),
                'refunded_at' => $refund ? now() : null,
            ]);
        }, 3);
    }

    private function findActivationByOrderId(SmsVirtualClient $client, string $providerOrderId): ?array
    {
        $lookups = [
            fn () => $client->ongoingActivations(['page' => 1, 'pageSize' => 50]),
            fn () => $client->orderHistory(['page' => 1, 'pageSize' => 50]),
            fn () => $client->activationHistory(['page' => 1, 'pageSize' => 50]),
        ];

        foreach ($lookups as $lookup) {
            try {
                $response = $lookup();
            } catch (Throwable $e) {
                // Satu endpoint history gagal tidak boleh membuat placement baru.
                // Lanjutkan ke sumber lain; retry queue tetap menangani jika semua
                // sumber belum menampilkan activation.
                report($e);
                continue;
            }

            $rows = $response['data'] ?? [];
            if (! is_array($rows)) continue;

            foreach ($rows as $row) {
                if (! is_array($row)) continue;

                $rowOrderId = $this->findValue($row, ['orderId', 'order.id']);
                $rowDetailId = $this->findValue($row, ['id']);
                if ($rowOrderId && (string) $rowOrderId === $providerOrderId && is_string($rowDetailId) && Str::isUuid($rowDetailId)) {
                    return $row;
                }

                // /orders/history berbentuk order -> orderDetail[].
                if ((string) ($row['id'] ?? '') !== $providerOrderId) continue;

                $details = $row['orderDetail'] ?? [];
                if (! is_array($details)) continue;

                foreach ($details as $detail) {
                    if (! is_array($detail)) continue;
                    $detailId = $this->findValue($detail, ['id']);
                    if (! is_string($detailId) || ! Str::isUuid($detailId)) continue;

                    if (! isset($detail['orderId'])) $detail['orderId'] = $providerOrderId;
                    return $detail;
                }
            }
        }

        return null;
    }

    private function providerDate(mixed $value): ?Carbon
    {
        if (! $value) return null;

        try {
            // Provider dapat mengirim timestamp UTC (mis. ...Z). Sebelum
            // disimpan ke kolom DATETIME, ubah dulu ke timezone aplikasi agar
            // clock time tidak bergeser -8 jam saat dibaca kembali di WITA.
            return Carbon::parse($value)->setTimezone((string) config('app.timezone', 'Asia/Makassar'));
        } catch (Throwable) {
            return null;
        }
    }

    private function findValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if ($value !== null && $value !== '') return $value;
        }
        return null;
    }
}
