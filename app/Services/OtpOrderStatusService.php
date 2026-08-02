<?php

namespace App\Services;

use App\Models\OtpOrder;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class OtpOrderStatusService
{
    public function __construct(
        private readonly SmsVirtualClient $client,
        private readonly WalletService $wallet,
        private readonly Settings $settings,
    ) {}

    public function sync(OtpOrder $order): OtpOrder
    {
        if (! $order->shouldPoll() || ! $order->provider_activation_id) return $order->refresh();
        $response = $this->client->getStatus($order->provider_activation_id);
        return $this->apply($order, $response);
    }

    public function apply(OtpOrder $order, array $response): OtpOrder
    {
        $payload = $this->unwrap($response);
        $activationStatus = $this->intValue($payload, ['statusActivation', 'activation.status', 'activationStatus']);
        $orderStatus = $this->intValue($payload, ['statusOrder', 'order.status', 'orderStatus']);
        $otp = $this->stringValue($payload, ['otp', 'code', 'smsCode', 'activation.otp', 'activation.code', 'sms.code']);
        $phone = $this->stringValue($payload, ['phoneNumber', 'number', 'phone', 'activation.phoneNumber', 'activation.number']);
        $message = $this->stringValue($payload, ['message', 'sms.message', 'activation.message']);
        $expires = $this->dateValue($payload, ['expiredAt', 'expiresAt', 'expirationDate', 'activation.expiredAt']);
        $providerStatus = $this->localStatus($activationStatus, $orderStatus, $otp);

        DB::transaction(function () use ($order, $response, $activationStatus, $orderStatus, $otp, $phone, $message, $expires, $providerStatus): void {
            $locked = OtpOrder::query()->lockForUpdate()->findOrFail($order->id);
            $effectiveStatus = $providerStatus;

            // Jangan menurunkan pesanan yang sudah memiliki OTP menjadi expired/cancelled
            // hanya karena respons polling berikutnya tidak lagi menyertakan kode.
            if ($locked->hasOtp() && ! $otp && ! in_array($providerStatus, ['completed', 'refunded'], true)) {
                $effectiveStatus = 'otp_received';
            }

            $updates = [
                'provider_activation_status' => $activationStatus,
                'provider_order_status' => $orderStatus,
                'provider_message' => $message,
                'provider_payload' => $response,
                'last_synced_at' => now(),
                'status' => $effectiveStatus,
            ];
            if ($phone) $updates['phone_number'] = $phone;
            if ($otp) {
                $updates['otp_code'] = $otp;
                $updates['otp_received_at'] = $locked->otp_received_at ?: now();
                $updates['status'] = 'otp_received';
            }
            if ($expires) $updates['expires_at'] = $expires;
            if ($effectiveStatus === 'completed') $updates['completed_at'] = $locked->completed_at ?: now();
            $locked->update($updates);

            $providerRefunded = $providerStatus === 'refunded';
            $autoRefundExpired = $providerStatus === 'expired'
                && ! $locked->hasOtp()
                && (bool) $this->settings->get('orders.refund_on_expired', false);

            if (($providerRefunded || $autoRefundExpired) && ! $locked->refunded_at) {
                $this->wallet->credit(
                    $locked->user,
                    (float) $locked->sell_price,
                    'order_refund',
                    'order-refund:'.$locked->id,
                    'Refund pesanan '.$locked->service_name,
                    OtpOrder::class,
                    $locked->id,
                    ['provider_status' => $providerStatus, 'automatic_expiry_refund' => $autoRefundExpired],
                );
                $locked->update(['status' => 'refunded', 'refunded_at' => now()]);
            }
        }, 3);

        return $order->refresh();
    }

    public function action(OtpOrder $order, string $action): OtpOrder
    {
        if (! $order->provider_activation_id) return $order;
        $response = match ($action) {
            'ready' => $this->client->ready($order->provider_activation_id),
            'resend' => $this->client->resend($order->provider_activation_id),
            'cancel' => $this->client->cancel($order->provider_activation_id),
            'complete' => $this->client->complete($order->provider_activation_id),
            'reactivate' => $this->client->reactivate($order->provider_activation_id),
            default => throw new \InvalidArgumentException('Aksi pesanan tidak dikenali.'),
        };
        $updated = $this->apply($order, $response);
        if ($action === 'cancel' && ! $updated->hasOtp() && ! $updated->refunded_at) {
            try {
                $this->wallet->credit($updated->user, (float) $updated->sell_price, 'order_refund', 'order-refund:'.$updated->id, 'Refund pembatalan '.$updated->service_name, OtpOrder::class, $updated->id);
                $updated->update(['status' => 'cancelled', 'refunded_at' => now()]);
            } catch (Throwable) {}
        }
        if ($action === 'complete') $updated->update(['status' => 'completed', 'completed_at' => now()]);
        return $updated->refresh();
    }

    private function unwrap(array $response): array
    {
        $payload = $response['data'] ?? $response;
        return is_array($payload) ? $payload : [];
    }

    private function intValue(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_numeric($value)) return (int) $value;
        }
        return null;
    }

    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value);
        }
        return null;
    }

    private function dateValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if ($value) {
                try {
                    return Carbon::parse($value);
                } catch (Throwable) {}
            }
        }
        return null;
    }

    private function localStatus(?int $activation, ?int $order, ?string $otp): string
    {
        if ($otp || $activation === 3 || $order === 1) return 'otp_received';
        if ($activation === 7 || $order === 4) return 'refunded';
        if ($activation === 8 || $order === 5) return 'cancel_pending';
        if ($activation === 5 || $order === 3) return 'cancelled';
        if ($activation === 6 || $order === 2) return 'expired';
        if ($activation === 4) return 'completed';
        if ($activation === 2) return 'resend_requested';
        if ($activation === 1) return 'ready';
        return 'pending';
    }
}
