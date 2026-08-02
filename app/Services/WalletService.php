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

    private function mutate(User $user, float $amount, string $direction, string $category, string $referenceKey, string $description, ?string $referenceType, ?string $referenceId, array $meta): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $direction, $category, $referenceKey, $description, $referenceType, $referenceId, $meta): WalletTransaction {
            $existing = WalletTransaction::query()->where('reference_key', $referenceKey)->first();
            if ($existing) return $existing;

            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = (float) $locked->balance;
            if ($direction === 'debit' && $before < $amount) {
                throw ValidationException::withMessages(['balance' => 'Saldo tidak mencukupi. Silakan top up terlebih dahulu.']);
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
