<?php

namespace App\Services;

use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public const PAKASIR = 'pakasir';
    public const DUITKU = 'duitku';

    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function activeGateway(): string
    {
        $gateway = strtolower((string) $this->settings->get('payments.active_gateway', self::PAKASIR));

        return in_array($gateway, [self::PAKASIR, self::DUITKU], true)
            ? $gateway
            : self::PAKASIR;
    }

    public function pendingGateway(): ?string
    {
        $gateway = strtolower(trim((string) $this->settings->get('payments.pending_gateway', '')));

        return in_array($gateway, [self::PAKASIR, self::DUITKU], true)
            ? $gateway
            : null;
    }

    public function requestSwitch(string $target, ?User $actor = null): array
    {
        return $this->withSwitchLock(function () use ($target, $actor): array {
            $target = strtolower($target);
            if (! in_array($target, [self::PAKASIR, self::DUITKU], true)) {
                throw new InvalidArgumentException('Penyedia pembayaran tidak dikenali.');
            }

            $current = $this->activeGateway();
            if ($target === $current) {
                $this->clearPending();

                return [
                    'state' => 'unchanged',
                    'active' => $current,
                    'pending' => null,
                    'blockers' => $this->blockingCounts(),
                ];
            }

            // Masuk mode drain lebih dulu. Request top-up/order baru akan ditahan
            // selama jendela pergantian sehingga transaksi baru tidak lolos di antara
            // pemeriksaan blocker dan perubahan gateway aktif.
            $this->settings->setMany([
                'payments.pending_gateway' => $target,
                'payments.pending_requested_at' => now()->toIso8601String(),
                'payments.pending_requested_by' => $actor?->id,
            ]);

            $blockers = $this->blockingCounts();
            if (($blockers['topups'] + $blockers['orders']) > 0) {
                $this->activity->gatewaySwitch(
                    'payment_gateway.switch_scheduled',
                    $current,
                    $target,
                    $actor,
                    ['blockers' => $blockers],
                );

                return [
                    'state' => 'scheduled',
                    'active' => $current,
                    'pending' => $target,
                    'blockers' => $blockers,
                ];
            }

            $this->apply($current, $target, $actor);

            return [
                'state' => 'applied',
                'active' => $target,
                'pending' => null,
                'blockers' => $blockers,
            ];
        });
    }

    public function applyPendingIfSafe(): bool
    {
        return $this->withSwitchLock(function (): bool {
            $pending = $this->pendingGateway();
            if (! $pending) {
                return false;
            }

            $blockers = $this->blockingCounts();
            if (($blockers['topups'] + $blockers['orders']) > 0) {
                return false;
            }

            $from = $this->activeGateway();
            if ($pending === $from) {
                $this->clearPending();
                return false;
            }

            $requestedBy = $this->settings->get('payments.pending_requested_by');
            $actor = is_numeric($requestedBy) ? User::query()->find((int) $requestedBy) : null;
            $this->apply($from, $pending, $actor, ['automatic_after_queue_drained' => true]);

            return true;
        });
    }

    /**
     * Menutup race condition antara transaksi baru dan pergantian gateway.
     * Kunci hanya dipegang saat status drain/gateway dibaca dan row transaksi
     * lokal dibuat; panggilan HTTP ke provider dilakukan setelah kunci dilepas.
     */
    public function withSwitchLock(callable $callback): mixed
    {
        return Cache::lock('kodeotp:payment-gateway-switch', 15)->block(8, $callback);
    }

    public function blockingCounts(): array
    {
        return [
            'topups' => Topup::query()
                ->whereIn('status', ['creating', 'pending'])
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'orders' => OtpOrder::query()->whereNotIn('status', [
                'completed',
                'cancelled',
                'expired',
                'refunded',
                'failed',
            ])->count(),
        ];
    }

    public function label(?string $gateway = null): string
    {
        return ($gateway ?? $this->activeGateway()) === self::DUITKU ? 'Duitku' : 'Pakasir';
    }

    public function paymentMethods(?string $gateway = null): array
    {
        $gateway ??= $this->activeGateway();

        if ($gateway === self::DUITKU) {
            $method = strtoupper((string) $this->settings->get('duitku.payment_method', 'NQ'));
            $all = $this->duitkuMethodOptions();

            return [$method => $all[$method] ?? 'Duitku '.$method];
        }

        return [
            'qris' => 'QRIS',
            'cimb_niaga_va' => 'CIMB Niaga VA',
            'bni_va' => 'BNI VA',
            'sampoerna_va' => 'Sampoerna VA',
            'bnc_va' => 'BNC VA',
            'maybank_va' => 'Maybank VA',
            'permata_va' => 'Permata VA',
            'atm_bersama_va' => 'ATM Bersama VA',
            'artha_graha_va' => 'Artha Graha VA',
            'bri_va' => 'BRI VA',
        ];
    }

    public function duitkuMethodOptions(): array
    {
        return [
            'NQ' => 'QRIS Nobu',
            'GQ' => 'QRIS Gudang Voucher',
            'SQ' => 'QRIS Nusapay',
            'SP' => 'QRIS ShopeePay',
            'BC' => 'BCA Virtual Account',
            'M2' => 'Mandiri Virtual Account',
            'VA' => 'Maybank Virtual Account',
            'I1' => 'BNI Virtual Account',
            'B1' => 'CIMB Niaga Virtual Account',
            'BT' => 'Permata Virtual Account',
            'A1' => 'ATM Bersama',
            'AG' => 'Bank Artha Graha',
            'NC' => 'Bank Neo Commerce / BNC',
            'BR' => 'BRIVA',
            'S1' => 'Bank Sahabat Sampoerna',
            'DM' => 'Danamon Virtual Account',
            'BV' => 'BSI Virtual Account',
        ];
    }

    public function isQrisMethod(string $gateway, string $method): bool
    {
        if ($gateway === self::DUITKU) {
            return in_array(strtoupper($method), ['NQ', 'GQ', 'SQ', 'SP'], true);
        }

        return strtolower($method) === 'qris';
    }

    private function apply(string $from, string $to, ?User $actor = null, array $meta = []): void
    {
        $this->settings->set('payments.active_gateway', $to);
        $this->clearPending();
        $this->activity->gatewaySwitch('payment_gateway.switched', $from, $to, $actor, $meta);
    }

    private function clearPending(): void
    {
        $this->settings->setMany([
            'payments.pending_gateway' => null,
            'payments.pending_requested_at' => null,
            'payments.pending_requested_by' => null,
        ]);
    }
}
