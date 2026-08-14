<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActivityLogger
{
    public function topupCreated(Topup $topup): void
    {
        $this->write([
            'user_id' => $topup->user_id,
            'event' => 'topup.created',
            'subject_type' => 'topup',
            'subject_id' => (string) $topup->id,
            'gateway' => $topup->gateway ?: 'paykita',
            'status' => $topup->status,
            'amount' => $topup->amount,
            'description' => sprintf(
                '%s membuat isi saldo Rp %s melalui %s.',
                $this->userLabel($topup->user),
                number_format((float) $topup->amount, 0, ',', '.'),
                $this->gatewayLabel($topup->gateway),
            ),
            'meta' => [
                'order_id' => $topup->order_id,
                'payment_method' => $topup->payment_method,
            ],
        ]);
    }

    public function topupStatusChanged(Topup $topup, string $from, string $to): void
    {
        $this->write([
            'user_id' => $topup->user_id,
            'event' => 'topup.'.$to,
            'subject_type' => 'topup',
            'subject_id' => (string) $topup->id,
            'gateway' => $topup->gateway ?: 'paykita',
            'status' => $to,
            'amount' => $topup->amount,
            'description' => sprintf(
                'Isi saldo %s milik %s melalui %s berubah dari %s menjadi %s.',
                $topup->order_id,
                $this->userLabel($topup->user),
                $this->gatewayLabel($topup->gateway),
                $this->statusLabel($from),
                $this->statusLabel($to),
            ),
            'meta' => [
                'order_id' => $topup->order_id,
                'from' => $from,
                'to' => $to,
                'payment_method' => $topup->payment_method,
            ],
        ]);
    }

    public function orderCreated(OtpOrder $order): void
    {
        $this->write([
            'user_id' => $order->user_id,
            'event' => 'order.created',
            'subject_type' => 'order',
            'subject_id' => (string) $order->id,
            'status' => $order->status,
            'amount' => $order->sell_price,
            'description' => sprintf(
                '%s membeli layanan %s (%s) senilai Rp %s.',
                $this->userLabel($order->user),
                $order->service_name,
                $order->country_name,
                number_format((float) $order->sell_price, 0, ',', '.'),
            ),
            'meta' => [
                'service' => $order->service_name,
                'country' => $order->country_name,
                'operator' => $order->operator_name,
            ],
        ]);
    }

    public function orderStatusChanged(OtpOrder $order, string $from, string $to): void
    {
        $user = $this->userLabel($order->user);
        $amount = number_format((float) $order->sell_price, 0, ',', '.');
        $description = match ($to) {
            'cancelled' => sprintf('Pesanan %s (%s) milik %s senilai Rp %s dibatalkan.', $order->service_name, $order->country_name, $user, $amount),
            'refunded' => sprintf('Pengembalian dana pesanan %s (%s) milik %s senilai Rp %s diproses.', $order->service_name, $order->country_name, $user, $amount),
            'completed' => sprintf('Pesanan %s (%s) milik %s senilai Rp %s selesai.', $order->service_name, $order->country_name, $user, $amount),
            'failed' => sprintf('Pesanan %s (%s) milik %s senilai Rp %s gagal.', $order->service_name, $order->country_name, $user, $amount),
            'expired' => sprintf('Pesanan %s (%s) milik %s senilai Rp %s kedaluwarsa.', $order->service_name, $order->country_name, $user, $amount),
            default => sprintf(
                'Pesanan %s milik %s berubah dari %s menjadi %s.',
                $order->service_name,
                $user,
                $this->statusLabel($from),
                $this->statusLabel($to),
            ),
        };

        $this->write([
            'user_id' => $order->user_id,
            'event' => 'order.'.$to,
            'subject_type' => 'order',
            'subject_id' => (string) $order->id,
            'status' => $to,
            'amount' => $order->sell_price,
            'description' => $description,
            'meta' => [
                'from' => $from,
                'to' => $to,
                'service' => $order->service_name,
                'country' => $order->country_name,
            ],
        ]);
    }

    public function gatewaySwitch(
        string $event,
        string $from,
        string $to,
        ?User $actor = null,
        array $meta = [],
    ): void {
        $scheduled = $event === 'payment_gateway.switch_scheduled';

        $this->write([
            'actor_id' => $actor?->id,
            'event' => $event,
            'subject_type' => 'payment_gateway',
            'gateway' => $to,
            'status' => $scheduled ? 'scheduled' : 'active',
            'description' => $scheduled
                ? sprintf(
                    'Peralihan penyedia pembayaran dari %s ke %s dijadwalkan sampai transaksi aktif selesai.',
                    $this->gatewayLabel($from),
                    $this->gatewayLabel($to),
                )
                : sprintf(
                    'Penyedia pembayaran aktif beralih dari %s ke %s.',
                    $this->gatewayLabel($from),
                    $this->gatewayLabel($to),
                ),
            'meta' => array_merge(['from' => $from, 'to' => $to], $meta),
        ]);
    }

    private function write(array $attributes): void
    {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }

            ActivityLog::query()->create($attributes);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function userLabel(?User $user): string
    {
        return $user?->email ?: $user?->name ?: 'Pengguna';
    }

    private function gatewayLabel(?string $gateway): string
    {
        return match (strtolower((string) $gateway)) {
            'paykita' => 'PayKita',
            default => ucfirst($gateway ?: 'PayKita'),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'creating' => 'membuat transaksi',
            'processing' => 'diproses',
            'provider_pending' => 'menunggu penyedia',
            'pending' => 'menunggu',
            'otp_received' => 'OTP diterima',
            'completed' => 'selesai',
            'cancelled' => 'dibatalkan',
            'refunded' => 'dikembalikan',
            'expired' => 'kedaluwarsa',
            'failed' => 'gagal',
            default => str_replace('_', ' ', $status),
        };
    }
}
