<?php

namespace App\Jobs;

use App\Models\OtpOrder;
use App\Services\OtpOrderStatusService;
use App\Services\SmsVirtualClient;
use App\Services\WalletService;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        if (! $order->provider_price_id) {
            $this->terminalFailure($order, 'Referensi harga provider tidak tersedia.', $wallet);
            return;
        }

        try {
            $response = $client->requestSingleService(array_filter([
                'serviceCountryPriceId' => $order->provider_price_id,
                'operatorId' => $order->provider_operator_id,
                'quantity' => 1,
                'autoSearchServer' => true,
            ], fn ($value) => $value !== null && $value !== ''), $order->idempotency_key);

            $provider = $response['data'] ?? $response;
            if (! is_array($provider)) $provider = [];
            $activationId = $this->findValue($provider, [
                'activationId',
                'activation.activationId',
                'activation.id',
                'activations.0.activationId',
                'activations.0.id',
                'items.0.activationId',
                'items.0.id',
                '0.activationId',
                '0.id',
                'order.activationId',
                'order.activation.id',
                'id',
            ]);
            if (! $activationId) throw new RuntimeException('Provider belum mengembalikan activation ID.', 503);

            $providerOrderId = $this->findValue($provider, ['orderId', 'order.id', 'invoiceNo', 'order.invoiceNo', 'activations.0.orderId']);
            $expiresAt = $this->findValue($provider, [
                'expiredAt',
                'expiresAt',
                'activation.expiredAt',
                'activation.expiresAt',
                'activations.0.expiredAt',
                'activations.0.expiresAt',
                '0.expiredAt',
                '0.expiresAt',
            ])
                ?: now()->addMinutes((int) $settings->get('orders.default_expiry_minutes', 20));

            $cancelProviderActivation = false;

            DB::transaction(function () use ($order, $response, $activationId, $providerOrderId, $expiresAt, &$cancelProviderActivation): void {
                $locked = OtpOrder::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->provider_activation_id) return;

                // User dapat membatalkan ketika request ke provider masih in-flight.
                // Jangan menghidupkan lagi order lokal yang sudah dibatalkan/refund.
                if ($locked->refunded_at || in_array($locked->status, ['cancelled', 'refunded'], true)) {
                    $cancelProviderActivation = true;
                    return;
                }

                $locked->update([
                    'provider_activation_id' => (string) $activationId,
                    'provider_order_id' => $providerOrderId ? (string) $providerOrderId : null,
                    'provider_payload' => $response,
                    'provider_message' => null,
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                ]);
            }, 3);

            if ($cancelProviderActivation) {
                try {
                    $client->cancel((string) $activationId);
                } catch (Throwable $cancelError) {
                    report($cancelError);
                }
                return;
            }

            $fresh = $order->refresh();
            if ($fresh->provider_activation_id) {
                $statusService->apply($fresh, $response);
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
            $refund = $wallet->refundDebit(
                $locked->user,
                'order-debit:'.$locked->id,
                'order-refund:'.$locked->id,
                'Refund pesanan gagal '.$locked->service_name,
                OtpOrder::class,
                $locked->id,
                ['reason' => str($message)->limit(500)->toString()],
            );
            $locked->update([
                'status' => 'failed',
                'provider_message' => str($message)->limit(1000)->toString(),
                'refunded_at' => $refund ? now() : null,
            ]);
        }, 3);
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
