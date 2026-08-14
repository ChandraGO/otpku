<?php

namespace App\Services;

use App\Models\OtpOrder;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

        // Endpoint status/action SMS Virtual mengharapkan UUID order-detail
        // (field `id` pada record activation), bukan numeric `activationId`
        // dan bukan UUID order. Perbaiki otomatis order lama yang sempat
        // menyimpan salah satu identifier tersebut.
        $providerActionId = $this->providerActionId($order);
        $response = $this->client->getStatus($providerActionId);

        return $this->apply($order->refresh(), $response);
    }

    public function apply(OtpOrder $order, array $response): OtpOrder
    {
        $payload = $this->unwrap($response);
        $activationStatus = $this->intValue($payload, ['statusActivation', 'status', 'activation.statusActivation', 'activation.status', 'activationStatus', 'activations.0.statusActivation', 'activations.0.status', '0.statusActivation', '0.status']);
        $orderStatus = $this->intValue($payload, ['statusOrder', 'order.statusOrder', 'order.status', 'orderStatus', 'activations.0.statusOrder', '0.statusOrder']);
        $otp = $this->stringValue($payload, ['otp', 'code', 'smsCode', 'orderDetailOtp.0.otp', 'activation.otp', 'activation.code', 'activation.orderDetailOtp.0.otp', 'sms.code', 'activations.0.otp', 'activations.0.code', 'activations.0.orderDetailOtp.0.otp', '0.otp', '0.code']);
        $phone = $this->stringValue($payload, ['phoneNumber', 'number', 'phone', 'activation.phoneNumber', 'activation.number', 'activations.0.phoneNumber', 'activations.0.number', '0.phoneNumber', '0.number']);
        $message = $this->stringValue($payload, ['message', 'fullMessage', 'orderDetailOtp.0.fullMessage', 'sms.message', 'activation.message', 'activation.orderDetailOtp.0.fullMessage', 'activations.0.message', 'activations.0.orderDetailOtp.0.fullMessage', '0.message']);
        $expires = $this->dateValue($payload, ['expiredTime', 'expiredAt', 'expiresAt', 'expirationDate', 'activation.expiredTime', 'activation.expiredAt', 'activation.expiresAt', 'activations.0.expiredTime', 'activations.0.expiredAt', 'activations.0.expiresAt', '0.expiredTime', '0.expiredAt', '0.expiresAt']);
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
            $providerCancelled = $providerStatus === 'cancelled' && ! $locked->hasOtp();
            $autoRefundExpired = $providerStatus === 'expired'
                && ! $locked->hasOtp()
                && (bool) $this->settings->get('orders.refund_on_expired', false);

            if (($providerRefunded || $providerCancelled || $autoRefundExpired) && ! $locked->refunded_at) {
                $refund = $this->wallet->refundOrderPayment(
                    $locked,
                    'Refund pesanan '.$locked->service_name,
                    [
                        'provider_status' => $providerStatus,
                        'automatic_expiry_refund' => $autoRefundExpired,
                    ],
                );

                // refunded_at represents a local wallet refund. Administrator
                // orders use provider balance directly and therefore have no
                // wallet debit/refund to record.
                if ($refund) {
                    $locked->update(['status' => 'refunded', 'refunded_at' => now()]);
                } elseif ($providerRefunded) {
                    $locked->update(['status' => 'refunded']);
                }
            }
        }, 3);

        return $order->refresh();
    }

    public function action(OtpOrder $order, string $action): OtpOrder
    {
        $order = $order->refresh();

        if (! $order->provider_activation_id) {
            if ($action === 'cancel' && in_array($order->status, ['processing', 'provider_pending'], true)) {
                return $this->cancelBeforeActivation($order);
            }

            throw new \RuntimeException('Nomor masih diproses oleh provider. Aksi ini tersedia setelah nomor berhasil dialokasikan.');
        }

        if ($action === 'cancel' && $order->hasOtp()) {
            throw new \RuntimeException('Pesanan yang sudah menerima OTP tidak dapat dibatalkan. Gunakan Selesaikan setelah OTP digunakan.');
        }

        if ($action === 'reactivate' && ! in_array($order->status, ['cancelled', 'expired', 'failed'], true)) {
            throw new \RuntimeException('Aktifkan ulang hanya tersedia untuk aktivasi yang cancelled, expired, atau gagal dan masih dapat dipulihkan provider.');
        }

        $providerActionId = $this->providerActionId($order);

        $response = match ($action) {
            'ready' => $this->client->ready($providerActionId),
            'resend' => $this->client->resend($providerActionId),
            'cancel' => $this->client->cancel($providerActionId),
            'complete' => $this->client->complete($providerActionId),
            'reactivate' => $this->client->reactivate($providerActionId),
            default => throw new \InvalidArgumentException('Aksi pesanan tidak dikenali.'),
        };

        $updated = $this->apply($order, $response);

        // Refund pembatalan hanya diberikan setelah provider mengonfirmasi
        // status cancelled/refunded di apply(). Jangan kredit saldo saat masih
        // cancel_pending karena provider belum tentu mengembalikan dana.
        if ($action === 'complete') {
            $updated->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return $updated->refresh();
    }

    private function cancelBeforeActivation(OtpOrder $order): OtpOrder
    {
        $activationAppeared = false;

        DB::transaction(function () use ($order, &$activationAppeared): void {
            /** @var OtpOrder $locked */
            $locked = OtpOrder::query()->with('user')->lockForUpdate()->findOrFail($order->id);

            // Worker dapat selesai tepat ketika user menekan Batalkan. Dalam
            // kondisi race ini jangan membatalkan lokal; lanjutkan cancel ke
            // provider setelah transaksi DB dilepas.
            if ($locked->provider_activation_id) {
                $activationAppeared = true;
                return;
            }

            if (! in_array($locked->status, ['processing', 'provider_pending'], true)) {
                throw new \RuntimeException('Pesanan ini sudah tidak dapat dibatalkan sebelum aktivasi.');
            }

            $refund = $this->wallet->refundOrderPayment(
                $locked,
                'Refund pesanan dibatalkan sebelum nomor tersedia '.$locked->service_name,
                ['reason' => 'cancelled_before_provider_activation'],
            );

            $locked->update([
                'status' => $refund ? 'refunded' : 'cancelled',
                'refunded_at' => $refund ? now() : $locked->refunded_at,
                'provider_message' => 'Pesanan dibatalkan sebelum provider mengalokasikan nomor.',
                'last_synced_at' => now(),
            ]);
        }, 3);

        $fresh = $order->refresh();

        if ($activationAppeared || $fresh->provider_activation_id) {
            return $this->action($fresh, 'cancel');
        }

        return $fresh;
    }

    private function providerActionId(OtpOrder $order): string
    {
        $order = $order->refresh();
        $current = trim((string) $order->provider_activation_id);
        $providerOrderId = trim((string) ($order->provider_order_id ?? ''));

        // Identifier yang valid untuk endpoint status/action adalah UUID
        // order-detail. Numeric activationId jelas bukan UUID. Versi lama
        // juga sempat menyimpan UUID order ke kolom ini; itu dapat dikenali
        // karena nilainya sama dengan provider_order_id.
        $needsRepair = ! Str::isUuid($current)
            || ($providerOrderId !== '' && hash_equals($providerOrderId, $current));

        if (! $needsRepair) return $current;

        $record = $this->findActivationRecord($providerOrderId !== '' ? $providerOrderId : $current);
        $actionId = trim((string) ($record['id'] ?? ''));

        if (! Str::isUuid($actionId)) {
            throw new \RuntimeException('Identifier aktivasi provider belum dapat dipulihkan. Sistem akan mencoba sinkron lagi otomatis.');
        }

        $updates = ['provider_activation_id' => $actionId];
        $phone = $this->stringValue($record, ['phoneNumber', 'number', 'phone']);
        $expires = $this->dateValue($record, ['expiredTime', 'expiredAt', 'expiresAt']);
        if ($phone) $updates['phone_number'] = $phone;
        if ($expires) $updates['expires_at'] = $expires;
        if ($providerOrderId === '') {
            $resolvedOrderId = $this->stringValue($record, ['orderId', 'order.id']);
            if ($resolvedOrderId) $updates['provider_order_id'] = $resolvedOrderId;
        }
        $order->update($updates);

        // Terapkan snapshot activation dari history/ongoing agar status expired,
        // cancelled, nomor, durasi, dan OTP dapat pulih bahkan sebelum request
        // getStatus berikutnya selesai.
        $this->apply($order->refresh(), ['data' => $record]);

        return $actionId;
    }

    private function findActivationRecord(string $providerOrderId): array
    {
        if ($providerOrderId === '') return [];

        $lookups = [
            fn () => $this->client->ongoingActivations(['page' => 1, 'pageSize' => 50]),
            fn () => $this->client->orderHistory(['page' => 1, 'pageSize' => 50]),
            fn () => $this->client->activationHistory(['page' => 1, 'pageSize' => 50]),
        ];

        foreach ($lookups as $lookup) {
            try {
                $response = $lookup();
            } catch (Throwable $e) {
                report($e);
                continue;
            }

            $rows = $response['data'] ?? [];
            if (! is_array($rows)) continue;

            foreach ($rows as $row) {
                if (! is_array($row)) continue;

                $rowOrderId = $this->stringValue($row, ['orderId', 'order.id']);
                if ($rowOrderId === $providerOrderId && Str::isUuid((string) ($row['id'] ?? ''))) {
                    return $row;
                }

                // /orders/history berbentuk order -> orderDetail[].
                if ((string) ($row['id'] ?? '') !== $providerOrderId) continue;
                $details = $row['orderDetail'] ?? [];
                if (! is_array($details)) continue;

                foreach ($details as $detail) {
                    if (! is_array($detail) || ! Str::isUuid((string) ($detail['id'] ?? ''))) continue;
                    if (! isset($detail['orderId'])) $detail['orderId'] = $providerOrderId;
                    return $detail;
                }
            }
        }

        return [];
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
