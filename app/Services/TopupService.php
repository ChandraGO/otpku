<?php

namespace App\Services;

use App\Models\Topup;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TopupService
{
    public function __construct(private readonly PakasirClient $pakasir, private readonly WalletService $wallet) {}

    public function create(User $user, int $amount, string $method): Topup
    {
        $orderId = 'TOPUP-'.now()->format('ymdHis').'-'.str()->upper(str()->random(6));
        $topup = Topup::query()->create([
            'user_id' => $user->id, 'order_id' => $orderId, 'amount' => $amount, 'total_payment' => $amount,
            'payment_method' => $method, 'status' => 'creating',
        ]);
        try {
            $response = $this->pakasir->create($orderId, $amount, $method);
            $payment = $response['payment'] ?? $response['data']['payment'] ?? $response['data'] ?? $response;
            $topup->update([
                'fee' => (float) ($payment['fee'] ?? 0),
                'total_payment' => (float) ($payment['total_payment'] ?? $payment['totalPayment'] ?? $amount),
                'payment_number' => $payment['payment_number'] ?? $payment['paymentNumber'] ?? null,
                'checkout_url' => $this->pakasir->checkoutUrl($orderId, $amount),
                'expires_at' => $payment['expired_at'] ?? $payment['expiredAt'] ?? now()->addMinutes(30),
                'provider_payload' => $response,
                'status' => 'pending',
            ]);
            return $topup->refresh();
        } catch (\Throwable $e) {
            $topup->update(['status' => 'failed', 'provider_payload' => ['error' => $e->getMessage()]]);
            throw $e;
        }
    }

    public function verify(Topup $topup): Topup
    {
        if ($topup->credited_at) return $topup;
        $response = $this->pakasir->detail($topup->order_id, (int) $topup->amount);
        $tx = $response['transaction'] ?? $response['data']['transaction'] ?? $response['data'] ?? $response;
        $status = strtolower((string) ($tx['status'] ?? 'pending'));
        $project = (string) ($tx['project'] ?? '');
        $orderId = (string) ($tx['order_id'] ?? $tx['orderId'] ?? '');
        $amount = (int) ($tx['amount'] ?? 0);

        if ($project !== $this->pakasir->project() || $orderId !== $topup->order_id || $amount !== (int) $topup->amount) {
            throw new RuntimeException('Detail transaksi Pakasir tidak cocok dengan invoice lokal.');
        }
        if (! in_array($status, ['completed', 'success', 'paid'], true)) {
            if (in_array($status, ['expired', 'cancelled', 'failed'], true)) $topup->update(['status' => $status, 'provider_payload' => $response]);
            return $topup->refresh();
        }

        DB::transaction(function () use ($topup, $response, $tx): void {
            $locked = Topup::query()->lockForUpdate()->findOrFail($topup->id);
            if ($locked->credited_at) return;
            $this->wallet->credit($locked->user, (float) $locked->amount, 'topup', 'topup-credit:'.$locked->order_id, 'Top up saldo '.$locked->order_id, Topup::class, $locked->id, ['payment_method' => $locked->payment_method]);
            $locked->update([
                'status' => 'completed', 'paid_at' => Arr::get($tx, 'completed_at', now()), 'credited_at' => now(), 'provider_payload' => $response,
            ]);
        }, 3);
        return $topup->refresh();
    }
}
