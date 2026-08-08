<?php

namespace App\Services;

use App\Models\Topup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TopupService
{
    public function __construct(
        private readonly PakasirClient $pakasir,
        private readonly DuitkuClient $duitku,
        private readonly PaymentGatewayManager $gateways,
        private readonly WalletService $wallet,
    ) {}

    public function create(User $user, int $amount, string $method, ?string $expectedGateway = null): Topup
    {
        [$gateway, $topup] = $this->gateways->withSwitchLock(function () use ($user, $amount, $method, $expectedGateway): array {
            if ($this->gateways->pendingGateway()) {
                throw new RuntimeException('Pergantian penyedia pembayaran sedang diproses. Invoice baru ditahan sementara sampai pergantian selesai.');
            }

            $gateway = $this->gateways->activeGateway();
            if ($expectedGateway !== null && $gateway !== $expectedGateway) {
                throw new RuntimeException('Penyedia pembayaran berubah saat invoice sedang dibuat. Muat ulang halaman lalu coba lagi.');
            }

            if ($gateway === PaymentGatewayManager::DUITKU && $amount < 10000) {
                throw new RuntimeException('Minimum transaksi Duitku adalah Rp 10.000.');
            }

            $orderId = 'TOPUP-'.now()->format('ymdHis').'-'.str()->upper(str()->random(6));
            $topup = Topup::query()->create([
                'user_id' => $user->id,
                'order_id' => $orderId,
                'gateway' => $gateway,
                'amount' => $amount,
                'total_payment' => $amount,
                'payment_method' => $method,
                'status' => 'creating',
            ]);

            return [$gateway, $topup];
        });

        try {
            if ($gateway === PaymentGatewayManager::DUITKU) {
                return $this->createDuitku($topup, $user, $amount, $method);
            }

            return $this->createPakasir($topup, $amount, $method);
        } catch (Throwable $exception) {
            try {
                $topup->update([
                    'status' => 'failed',
                    'provider_payload' => ['error' => $this->safeErrorMessage($exception->getMessage())],
                ]);
            } catch (Throwable $loggingException) {
                report($loggingException);
            }

            throw $exception;
        }
    }

    public function verify(Topup $topup, bool $force = false): Topup
    {
        $topup = $this->normalizeStatus($topup);

        if ($topup->credited_at) {
            return $topup;
        }

        if (! $force && in_array($topup->status, ['completed', 'expired', 'cancelled', 'failed'], true)) {
            return $topup;
        }

        return ($topup->gateway ?: PaymentGatewayManager::PAKASIR) === PaymentGatewayManager::DUITKU
            ? $this->verifyDuitku($topup, $force)
            : $this->verifyPakasir($topup);
    }

    /**
     * Menutup invoice yang sudah tidak dapat dibayar supaya status tidak terus
     * terlihat Menunggu di halaman pengguna maupun laporan admin.
     */
    public function normalizeStatus(Topup $topup): Topup
    {
        $topup = $topup->refresh();

        if (! in_array($topup->status, ['creating', 'pending'], true)) {
            return $topup;
        }

        $expiredByTime = $topup->expires_at?->isPast() ?? false;
        $stuckCreating = $topup->status === 'creating'
            && $topup->created_at?->lte(now()->subMinutes(5));
        $noPaymentProcess = $topup->status === 'pending' && blank($topup->payment_number);

        if ($expiredByTime || $stuckCreating || $noPaymentProcess) {
            $topup->update(['status' => 'expired']);
            return $topup->refresh();
        }

        return $topup;
    }

    public function expireStale(): int
    {
        $count = 0;

        Topup::query()
            ->whereIn('status', ['creating', 'pending'])
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now()))
                    ->orWhere(fn ($q) => $q->where('status', 'creating')->where('created_at', '<=', now()->subMinutes(5)))
                    ->orWhere(fn ($q) => $q->where('status', 'pending')->whereNull('payment_number'));
            })
            ->orderBy('created_at')
            ->limit(500)
            ->get()
            ->each(function (Topup $topup) use (&$count): void {
                if ($this->normalizeStatus($topup)->status === 'expired') {
                    $count++;
                }
            });

        return $count;
    }

    public function cancel(Topup $topup, string $reason, ?string $note = null): Topup
    {
        $topup = $this->normalizeStatus($topup);

        if ($topup->status === 'cancelled') {
            return $topup;
        }

        if (! in_array($topup->status, ['creating', 'pending'], true) || $topup->credited_at) {
            throw new RuntimeException('Invoice ini sudah ditutup atau sudah diproses sehingga tidak dapat dibatalkan.');
        }

        // Pakasir menyediakan endpoint pembatalan. Kegagalan endpoint tidak
        // membuat UI menggantung; status provider tetap dapat dipulihkan oleh
        // webhook/verifikasi paksa bila ternyata pembayaran sudah terjadi.
        if (($topup->gateway ?: PaymentGatewayManager::PAKASIR) === PaymentGatewayManager::PAKASIR
            && $topup->status === 'pending') {
            try {
                $this->pakasir->cancel($topup->order_id, (int) $topup->amount);
            } catch (Throwable $exception) {
                report($exception);
                try {
                    $checked = $this->verify($topup, force: true);
                    if ($checked->credited_at || $checked->status === 'completed') {
                        throw new RuntimeException('Pembayaran sudah terkonfirmasi dan tidak dapat dibatalkan.');
                    }
                } catch (RuntimeException $verificationException) {
                    if (str_contains($verificationException->getMessage(), 'sudah terkonfirmasi')) {
                        throw $verificationException;
                    }
                } catch (Throwable $verificationException) {
                    report($verificationException);
                }
            }
        }

        return DB::transaction(function () use ($topup, $reason, $note): Topup {
            $locked = Topup::query()->lockForUpdate()->findOrFail($topup->id);

            if ($locked->credited_at || $locked->status === 'completed') {
                throw new RuntimeException('Pembayaran sudah terkonfirmasi dan tidak dapat dibatalkan.');
            }

            if ($locked->status === 'cancelled') {
                $locked->update([
                    'cancel_reason' => $reason,
                    'cancel_note' => filled($note) ? trim((string) $note) : null,
                    'cancelled_at' => $locked->cancelled_at ?: now(),
                ]);

                return $locked->refresh();
            }

            if (! in_array($locked->status, ['creating', 'pending'], true)) {
                return $locked;
            }

            $locked->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancel_note' => filled($note) ? trim((string) $note) : null,
                'cancelled_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function createPakasir(Topup $topup, int $amount, string $method): Topup
    {
        $response = $this->pakasir->create($topup->order_id, $amount, $method);
        $payment = $response['payment']
            ?? data_get($response, 'data.payment')
            ?? $response['data']
            ?? $response;

        if (! is_array($payment)) {
            throw new RuntimeException('Respons pembuatan transaksi Pakasir tidak valid.');
        }

        $paymentNumber = $payment['payment_number']
            ?? $payment['paymentNumber']
            ?? null;

        if (is_array($paymentNumber) || is_object($paymentNumber)) {
            $paymentNumber = json_encode(
                $paymentNumber,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        }

        if (is_string($paymentNumber) && $this->containsProviderCheckoutUrl($paymentNumber)) {
            $paymentNumber = null;
        }

        $topup->update([
            'fee' => (float) ($payment['fee'] ?? 0),
            'total_payment' => (float) (
                $payment['total_payment']
                ?? $payment['totalPayment']
                ?? $amount
            ),
            'payment_number' => filled($paymentNumber) ? (string) $paymentNumber : null,
            'provider_reference' => null,
            'checkout_url' => null,
            'expires_at' => $this->parsePakasirExpiry(
                $payment['expired_at']
                ?? $payment['expiredAt']
                ?? null,
            ),
            'provider_payload' => $this->sanitizeProviderPayload($response),
            'status' => 'pending',
        ]);

        return $topup->refresh();
    }

    private function createDuitku(Topup $topup, User $user, int $amount, string $method): Topup
    {
        $method = strtoupper($method);
        $available = collect($this->duitku->paymentMethods($amount))
            ->first(fn ($item) => is_array($item)
                && strtoupper((string) ($item['paymentMethod'] ?? '')) === $method);

        if (! is_array($available)) {
            throw new RuntimeException('Metode pembayaran Duitku '.$method.' tidak aktif pada proyek merchant ini.');
        }

        $fee = max(0, (float) ($available['totalFee'] ?? 0));
        $response = $this->duitku->create(
            $topup->order_id,
            $amount,
            $method,
            $user,
            route('topups.show', $topup),
            route('webhooks.duitku'),
        );

        $statusCode = (string) ($response['statusCode'] ?? '00');
        if ($statusCode !== '' && $statusCode !== '00') {
            throw new RuntimeException((string) ($response['statusMessage'] ?? 'Duitku gagal membuat transaksi.'));
        }

        $responseMerchant = trim((string) ($response['merchantCode'] ?? ''));
        $responseAmount = isset($response['amount']) ? (int) $response['amount'] : null;
        if (($responseMerchant !== '' && ! hash_equals($this->duitku->merchantCode(), $responseMerchant))
            || ($responseAmount !== null && $responseAmount !== $amount)) {
            throw new RuntimeException('Respons transaksi Duitku tidak cocok dengan invoice lokal.');
        }

        $reference = trim((string) ($response['reference'] ?? ''));
        if ($reference === '') {
            throw new RuntimeException('Duitku tidak mengembalikan reference transaksi.');
        }

        $paymentNumber = $response['qrString'] ?? $response['vaNumber'] ?? null;
        if (is_array($paymentNumber) || is_object($paymentNumber)) {
            $paymentNumber = json_encode($paymentNumber, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($paymentNumber) && $this->containsProviderCheckoutUrl($paymentNumber)) {
            $paymentNumber = null;
        }

        $topup->update([
            'fee' => $fee,
            'total_payment' => $amount + $fee,
            'payment_number' => filled($paymentNumber) ? (string) $paymentNumber : null,
            'provider_reference' => $reference,
            'checkout_url' => null,
            'expires_at' => now()->addMinutes($this->duitku->expiryMinutes()),
            'provider_payload' => $this->sanitizeProviderPayload($response),
            'status' => 'pending',
        ]);

        return $topup->refresh();
    }

    private function verifyPakasir(Topup $topup): Topup
    {
        $response = $this->pakasir->detail($topup->order_id, (int) $topup->amount);
        $tx = $response['transaction']
            ?? data_get($response, 'data.transaction')
            ?? $response['data']
            ?? $response;

        if (! is_array($tx)) {
            throw new RuntimeException('Respons detail transaksi Pakasir tidak valid.');
        }

        $status = strtolower((string) ($tx['status'] ?? 'pending'));
        $project = (string) ($tx['project'] ?? '');
        $orderId = (string) ($tx['order_id'] ?? $tx['orderId'] ?? '');
        $amount = (int) ($tx['amount'] ?? 0);
        $totalPayment = $tx['total_payment'] ?? $tx['totalPayment'] ?? null;

        if (
            $project !== $this->pakasir->project()
            || $orderId !== $topup->order_id
            || $amount !== (int) $topup->amount
            || (filled($totalPayment) && (int) $totalPayment !== (int) $topup->total_payment)
        ) {
            throw new RuntimeException('Detail transaksi Pakasir tidak cocok dengan invoice lokal.');
        }

        $safeResponse = $this->sanitizeProviderPayload($response);

        if (! in_array($status, ['completed', 'success', 'paid'], true)) {
            if (in_array($status, ['expired', 'cancelled', 'failed'], true)) {
                $topup->update([
                    'status' => $status,
                    'provider_payload' => $safeResponse,
                ]);
            }

            return $topup->refresh();
        }

        $this->creditVerifiedTopup(
            $topup,
            $safeResponse,
            Arr::get($tx, 'completed_at', now()),
            null,
        );

        return $topup->refresh();
    }

    private function verifyDuitku(Topup $topup, bool $force = false): Topup
    {
        // Duitku meminta merchant tidak melakukan cek transaksi berulang melalui
        // cron/polling agresif. Callback tetap jalur utama; polling halaman hanya
        // boleh memicu cek server-to-server paling sering sekali per menit.
        if (! $force && ! Cache::add('duitku:topup-status-check:'.$topup->id, true, 60)) {
            return $topup->refresh();
        }

        $response = $this->duitku->transactionStatus($topup->order_id);
        $orderId = (string) ($response['merchantOrderId'] ?? '');
        $reference = trim((string) ($response['reference'] ?? ''));
        $amount = (int) ($response['amount'] ?? 0);
        $providerFee = array_key_exists('fee', $response) ? (float) $response['fee'] : null;
        $statusCode = (string) ($response['statusCode'] ?? '');
        $statusMessage = strtoupper((string) ($response['statusMessage'] ?? ''));

        if ($orderId !== $topup->order_id || $amount !== (int) $topup->amount) {
            throw new RuntimeException('Detail transaksi Duitku tidak cocok dengan invoice lokal.');
        }

        if ((float) $topup->fee > 0 && $providerFee !== null && abs($providerFee - (float) $topup->fee) > 0.01) {
            throw new RuntimeException('Biaya transaksi Duitku tidak cocok dengan invoice lokal.');
        }

        if (filled($topup->provider_reference)
            && ($reference === '' || ! hash_equals((string) $topup->provider_reference, $reference))) {
            throw new RuntimeException('Reference transaksi Duitku tidak cocok dengan invoice lokal.');
        }

        $safeResponse = $this->sanitizeProviderPayload($response);

        if ($statusCode === '01') {
            return $topup->refresh();
        }

        if ($statusCode === '02') {
            $topup->update([
                'status' => str_contains($statusMessage, 'EXPIR') ? 'expired' : 'failed',
                'provider_payload' => $safeResponse,
            ]);

            return $topup->refresh();
        }

        if ($statusCode !== '00') {
            throw new RuntimeException('Status transaksi Duitku tidak dikenali: '.($statusMessage ?: $statusCode));
        }

        $this->creditVerifiedTopup($topup, $safeResponse, now(), $reference);

        return $topup->refresh();
    }

    private function creditVerifiedTopup(
        Topup $topup,
        array $safeResponse,
        mixed $paidAt,
        ?string $reference,
    ): void {
        DB::transaction(function () use ($topup, $safeResponse, $paidAt, $reference): void {
            $locked = Topup::query()->with('user')->lockForUpdate()->findOrFail($topup->id);

            if ($locked->credited_at) {
                return;
            }

            $this->wallet->credit(
                $locked->user,
                (float) $locked->amount,
                'topup',
                'topup-credit:'.$locked->order_id,
                'Top up saldo '.$locked->order_id,
                Topup::class,
                $locked->id,
                [
                    'gateway' => $locked->gateway ?: PaymentGatewayManager::PAKASIR,
                    'payment_method' => $locked->payment_method,
                    'provider_reference' => $reference ?: $locked->provider_reference,
                ],
            );

            $locked->update([
                'status' => 'completed',
                'paid_at' => $paidAt,
                'credited_at' => now(),
                'provider_reference' => $reference ?: $locked->provider_reference,
                'provider_payload' => $safeResponse,
            ]);
        }, 3);
    }

    private function containsProviderCheckoutUrl(string $value): bool
    {
        $normalized = rawurldecode(str_replace('\\/', '/', $value));

        return preg_match(
            '~https?://(?:[^/\s]+\.)?(?:pakasir\.com/pay|duitku\.com/(?:redirect|topup|checkout)|app-sandbox\.duitku\.com)(?:/|\?|$)~i',
            $normalized,
        ) === 1;
    }

    private function sanitizeProviderPayload(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $itemKey => $item) {
                $normalizedKey = strtolower((string) $itemKey);
                if (in_array($normalizedKey, ['paymenturl', 'appurl', 'checkout_url', 'checkouturl', 'qrstring'], true)) {
                    $safe[$itemKey] = '[disembunyikan]';
                    continue;
                }
                $safe[$itemKey] = $this->sanitizeProviderPayload($item, (string) $itemKey);
            }
            return $safe;
        }

        if (is_string($value) && $this->containsProviderCheckoutUrl($value)) {
            return '[tautan pembayaran penyedia disembunyikan]';
        }

        return $value;
    }

    private function safeErrorMessage(string $message): string
    {
        $value = $this->sanitizeProviderPayload($message);

        return is_string($value) ? $value : 'Penyedia pembayaran mengembalikan kesalahan.';
    }

    private function parsePakasirExpiry(mixed $value): CarbonImmutable
    {
        if (! filled($value)) {
            return now()->toImmutable()->addMinutes(30);
        }

        try {
            $normalized = preg_replace(
                '/(\.\d{6})\d+(?=Z|[+-]\d{2}:?\d{2}$)/',
                '$1',
                (string) $value,
            );

            return CarbonImmutable::parse($normalized)
                ->setTimezone((string) config('app.timezone', 'Asia/Makassar'));
        } catch (Throwable) {
            return now()->toImmutable()->addMinutes(30);
        }
    }
}
