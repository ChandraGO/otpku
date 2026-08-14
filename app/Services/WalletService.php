<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function credit(User $user, float $amount, string $category, string $referenceKey, string $description, ?string $referenceType = null, ?string $referenceId = null, array $meta = []): WalletTransaction
    {
        return $this->mutate($user, abs($amount), 'credit', $category, $referenceKey, $description, $referenceType, $referenceId, $meta);
    }

    public function debit(User $user, float $amount, string $category, string $referenceKey, string $description, ?string $referenceType = null, ?string $referenceId = null, array $meta = []): WalletTransaction
    {
        return $this->mutate($user, abs($amount), 'debit', $category, $referenceKey, $description, $referenceType, $referenceId, $meta);
    }

    /**
     * Refund only when the corresponding debit really exists. This prevents a
     * failed/cancelled order from creating money in an account that was never
     * charged (for example administrator orders that use provider balance).
     */
    public function refundDebit(
        User $user,
        string $debitReferenceKey,
        string $refundReferenceKey,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $meta = [],
    ): ?WalletTransaction {
        $debit = WalletTransaction::query()
            ->where('reference_key', $debitReferenceKey)
            ->where('user_id', $user->id)
            ->where('direction', 'debit')
            ->first();

        if (! $debit) {
            return null;
        }

        return $this->credit(
            $user,
            (float) $debit->amount,
            'order_refund',
            $refundReferenceKey,
            $description,
            $referenceType,
            $referenceId,
            [
                ...$meta,
                'original_debit_reference' => $debitReferenceKey,
                'original_debit_amount' => (float) $debit->amount,
            ],
        );
    }


    public function refundOrderPayment(\App\Models\OtpOrder $order, string $description, array $meta = []): ?WalletTransaction
    {
        $existing = WalletTransaction::query()->where('reference_key', 'order-refund:'.$order->id)->first();
        if ($existing) return $existing;

        if ($order->payment_channel === 'paykita' && $order->payment_status === 'paid') {
            return $this->credit(
                $order->user,
                (float) $order->sell_price,
                'order_refund',
                'order-refund:'.$order->id,
                $description,
                \App\Models\OtpOrder::class,
                $order->id,
                [...$meta, 'original_payment_channel' => 'paykita'],
            );
        }

        return $this->refundDebit(
            $order->user,
            'order-debit:'.$order->id,
            'order-refund:'.$order->id,
            $description,
            \App\Models\OtpOrder::class,
            $order->id,
            $meta,
        );
    }

    private function mutate(User $user, float $amount, string $direction, string $category, string $referenceKey, string $description, ?string $referenceType, ?string $referenceId, array $meta): WalletTransaction
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['balance' => 'Nominal transaksi saldo harus lebih dari 0.']);
        }

        return DB::transaction(function () use ($user, $amount, $direction, $category, $referenceKey, $description, $referenceType, $referenceId, $meta): WalletTransaction {
            $existing = WalletTransaction::query()->where('reference_key', $referenceKey)->first();
            if ($existing) {
                return $existing;
            }

            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = (float) $locked->balance;
            if ($direction === 'debit' && $before < $amount) {
                throw ValidationException::withMessages(['balance' => 'Saldo tidak mencukupi. Pilih pembayaran PayKita atau isi saldo terlebih dahulu.']);
            }
            $after = $direction === 'credit' ? $before + $amount : $before - $amount;
            $locked->forceFill(['balance' => $after])->save();

            return WalletTransaction::query()->create([
                'user_id' => $locked->id,
                'reference_key' => $referenceKey,
                'direction' => $direction,
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'meta' => $meta,
            ]);
        }, 3);
    }
}
