<?php

namespace App\Services;

use App\Models\Topup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TopupService
{
    public function __construct(
        private readonly PakasirClient $pakasir,
        private readonly WalletService $wallet,
    ) {}

    public function create(User $user, int $amount, string $method): Topup
    {
        $orderId = 'TOPUP-'.now()->format('ymdHis').'-'.str()->upper(str()->random(6));

        $topup = Topup::query()->create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'total_payment' => $amount,
            'payment_method' => $method,
            'status' => 'creating',
        ]);

        try {
            $response = $this->pakasir->create($orderId, $amount, $method);
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

            // Jika penyedia mengembalikan URL checkout sebagai nomor pembayaran,
            // jangan pernah teruskan nilai tersebut ke browser.
            if (is_string($paymentNumber) && $this->containsPakasirCheckoutUrl($paymentNumber)) {
                $paymentNumber = null;
            }

            $safeResponse = $this->redactPakasirCheckoutUrls($response);

            $topup->update([
                'fee' => (float) ($payment['fee'] ?? 0),
                'total_payment' => (float) (
                    $payment['total_payment']
                    ?? $payment['totalPayment']
                    ?? $amount
                ),
                'payment_number' => filled($paymentNumber)
                    ? (string) $paymentNumber
                    : null,
                // Jangan simpan atau kirim URL checkout provider ke browser.
                // Pembayaran ditampilkan dari payment_number/QRIS pada situs utama.
                'checkout_url' => null,
                'expires_at' => $this->parseExpiry(
                    $payment['expired_at']
                    ?? $payment['expiredAt']
                    ?? null,
                ),
                'provider_payload' => $safeResponse,
                'status' => 'pending',
            ]);

            return $topup->refresh();
        } catch (Throwable $exception) {
            try {
                $topup->update([
                    'status' => 'failed',
                    'provider_payload' => ['error' => (string) $this->redactPakasirCheckoutUrls($exception->getMessage())],
                ]);
            } catch (Throwable $loggingException) {
                report($loggingException);
            }

            throw $exception;
        }
    }

    public function verify(Topup $topup): Topup
    {
        if ($topup->credited_at) {
            return $topup;
        }

        $response = $this->pakasir->detail($topup->order_id, (int) $topup->amount);
        $tx = $response['transaction']
            ?? data_get($response, 'data.transaction')
            ?? $response['data']
            ?? $response;

        if (! is_array($tx)) {
            throw new RuntimeException('Respons detail transaksi Pakasir tidak valid.');
        }

        $safeResponse = $this->redactPakasirCheckoutUrls($response);

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

        if (! in_array($status, ['completed', 'success', 'paid'], true)) {
            if (in_array($status, ['expired', 'cancelled', 'failed'], true)) {
                $topup->update([
                    'status' => $status,
                    'provider_payload' => $safeResponse,
                ]);
            }

            return $topup->refresh();
        }

        DB::transaction(function () use ($topup, $safeResponse, $tx): void {
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
                ['payment_method' => $locked->payment_method],
            );

            $locked->update([
                'status' => 'completed',
                'paid_at' => Arr::get($tx, 'completed_at', now()),
                'credited_at' => now(),
                'provider_payload' => $safeResponse,
            ]);
        }, 3);

        return $topup->refresh();
    }

    private function containsPakasirCheckoutUrl(string $value): bool
    {
        $normalized = rawurldecode(str_replace('\\/', '/', $value));

        return preg_match(
            '~https?://(?:[^/\s]+\.)?pakasir\.com/pay(?:/|\?|$)~i',
            $normalized,
        ) === 1;
    }

    private function redactPakasirCheckoutUrls(mixed $value): mixed
    {
        if (is_array($value)) {
            $safe = [];

            foreach ($value as $key => $item) {
                $safe[$key] = $this->redactPakasirCheckoutUrls($item);
            }

            return $safe;
        }

        if (is_string($value) && $this->containsPakasirCheckoutUrl($value)) {
            return '[tautan pembayaran Pakasir disembunyikan]';
        }

        return $value;
    }

    private function parseExpiry(mixed $value): CarbonImmutable
    {
        if (! filled($value)) {
            return now()->toImmutable()->addMinutes(30);
        }

        try {
            // Some provider responses contain nanoseconds. PHP stores up to
            // microseconds, so trim the excess digits before parsing.
            $normalized = preg_replace(
                '/(\.\d{6})\d+(?=Z|[+-]\d{2}:?\d{2}$)/',
                '$1',
                (string) $value,
            );

            // Pakasir mengirim expired_at dalam UTC (akhiran Z).
            // Normalisasikan ke timezone aplikasi sebelum disimpan ke kolom DATETIME/TIMESTAMP
            // agar offset tidak hilang ketika model dibaca kembali.
            return CarbonImmutable::parse($normalized)
                ->setTimezone((string) config('app.timezone', 'Asia/Makassar'));
        } catch (Throwable) {
            return now()->toImmutable()->addMinutes(30);
        }
    }
}
