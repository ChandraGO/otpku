<?php

namespace App\Services;

use App\Jobs\PlaceOtpOrder;
use App\Models\OtpOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PayKitaPaymentService
{
    public function __construct(private readonly PayKitaClient $client) {}

    public function createForOrder(OtpOrder $order): OtpOrder
    {
        if ($order->payment_channel !== 'paykita') return $order;
        if ($order->paykita_order_id) return $order;

        $response = $this->client->createOrder(
            (int) round((float) $order->sell_price),
            'OTP-'.$order->id,
            route('orders.show', $order),
            route('webhooks.paykita'),
            $this->client->ttlSeconds(),
        );

        $data = $response['data'] ?? null;
        if (! is_array($data) || blank($data['id'] ?? null)) {
            throw new RuntimeException('Gateway pembayaran tidak mengembalikan ID transaksi yang valid.');
        }
        if ((string) ($data['reference'] ?? '') !== 'OTP-'.$order->id) {
            throw new RuntimeException('Referensi pembayaran tidak cocok dengan pesanan lokal.');
        }
        if (isset($data['base_amount']) && (int) $data['base_amount'] !== (int) round((float) $order->sell_price)) {
            throw new RuntimeException('Nominal pembayaran tidak cocok dengan harga produk.');
        }

        $fields = $this->paymentFields($data);
        $status = strtolower((string) ($data['status'] ?? 'pending'));
        if ($status === 'paid') {
            $fields['status'] = 'processing';
            $fields['payment_paid_at'] = $this->date($data['paid_at'] ?? null) ?: now();
            $fields['provider_message'] = 'Pembayaran terverifikasi. Nomor sedang diproses.';
        } elseif ($status === 'expired') {
            $fields['status'] = 'expired';
            $fields['provider_message'] = 'Pembayaran kedaluwarsa sebelum dibayar.';
        } elseif ($status === 'cancelled') {
            $fields['status'] = 'cancelled';
            $fields['provider_message'] = 'Pembayaran dibatalkan.';
        }

        $order->update($fields);
        if ($status === 'paid') PlaceOtpOrder::dispatch($order->id);
        return $order->refresh();
    }

    public function syncOrder(OtpOrder $order): OtpOrder
    {
        $order = $order->refresh();
        if ($order->payment_channel !== 'paykita' || blank($order->paykita_order_id)) return $order;
        if ($order->payment_status === 'paid') return $order;

        $response = $this->client->order((string) $order->paykita_order_id);
        $data = $response['data'] ?? null;
        if (! is_array($data)) throw new RuntimeException('Status pembayaran tidak valid.');

        $dispatch = false;
        DB::transaction(function () use ($order, $data, &$dispatch): void {
            /** @var OtpOrder $locked */
            $locked = OtpOrder::query()->lockForUpdate()->findOrFail($order->id);
            $remoteId = (string) ($data['id'] ?? '');
            $reference = (string) ($data['reference'] ?? '');
            if ($remoteId === '' || ! hash_equals((string) $locked->paykita_order_id, $remoteId)) {
                throw new RuntimeException('ID pembayaran tidak cocok dengan pesanan lokal.');
            }
            if ($reference !== '' && ! hash_equals('OTP-'.$locked->id, $reference)) {
                throw new RuntimeException('Referensi pembayaran tidak cocok dengan pesanan lokal.');
            }
            if (isset($data['base_amount']) && (int) $data['base_amount'] !== (int) round((float) $locked->sell_price)) {
                throw new RuntimeException('Nominal pembayaran tidak cocok dengan harga produk.');
            }

            $status = strtolower((string) ($data['status'] ?? 'pending'));
            $updates = $this->paymentFields($data);
            $updates['payment_status'] = $status;

            if ($status === 'paid') {
                $updates['status'] = 'processing';
                $updates['payment_paid_at'] = $this->date($data['paid_at'] ?? null) ?: now();
                $updates['provider_message'] = 'Pembayaran terverifikasi. Nomor sedang diproses.';
                $dispatch = ! $locked->provider_activation_id && ! in_array($locked->status, ['completed', 'refunded'], true);
            } elseif ($status === 'expired') {
                $updates['status'] = 'expired';
                $updates['provider_message'] = 'Pembayaran kedaluwarsa sebelum dibayar.';
            } elseif ($status === 'cancelled') {
                $updates['status'] = 'cancelled';
                $updates['provider_message'] = 'Pembayaran dibatalkan.';
            }

            $locked->update($updates);
        }, 3);

        if ($dispatch) PlaceOtpOrder::dispatch($order->id);
        return $order->refresh();
    }

    public function cancelOrder(OtpOrder $order): OtpOrder
    {
        $order = $order->refresh();
        if ($order->payment_channel !== 'paykita' || $order->payment_status !== 'pending' || blank($order->paykita_order_id)) {
            throw new RuntimeException('Pembayaran ini tidak dapat dibatalkan.');
        }
        $this->client->cancel((string) $order->paykita_order_id);
        return $this->syncOrder($order);
    }

    private function paymentFields(array $data): array
    {
        return [
            'paykita_order_id' => $data['id'] ?? null,
            'payment_status' => strtolower((string) ($data['status'] ?? 'pending')),
            'payment_base_amount' => isset($data['base_amount']) ? (int) $data['base_amount'] : null,
            'payment_fee_amount' => isset($data['fee_amount']) ? (int) $data['fee_amount'] : 0,
            'payment_unique_code' => isset($data['unique_code']) ? (int) $data['unique_code'] : 0,
            'payment_pay_amount' => isset($data['pay_amount']) ? (int) $data['pay_amount'] : null,
            'payment_qris' => $data['qris'] ?? null,
            'payment_checkout_url' => $data['checkout_url'] ?? null,
            'payment_expires_at' => $this->date($data['expires_at'] ?? null),
            'payment_paid_at' => $this->date($data['paid_at'] ?? null),
            'payment_payload' => $data,
        ];
    }

    private function date(mixed $value): mixed
    {
        if (! $value) return null;
        try { return Carbon::parse($value); } catch (Throwable) { return null; }
    }
}
