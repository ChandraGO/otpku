<?php

namespace App\Services;

use App\Models\Topup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TopupService
{
    public function __construct(private readonly PayKitaClient $paykita, private readonly WalletService $wallet) {}

    public function create(User $user, int $amount, string $method = 'qris', ?string $expectedGateway = null): Topup
    {
        $orderId = 'TOPUP-'.now()->format('ymdHis').'-'.str()->upper(str()->random(6));
        $topup = Topup::query()->create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'gateway' => 'paykita',
            'amount' => $amount,
            'total_payment' => $amount,
            'payment_method' => 'qris',
            'status' => 'creating',
        ]);

        try {
            $response = $this->paykita->createOrder($amount, $orderId, route('topups.show', $topup), route('webhooks.paykita'), $this->paykita->ttlSeconds());
            $data = $response['data'] ?? null;
            if (! is_array($data) || blank($data['id'] ?? null) || blank($data['qris'] ?? null)) throw new RuntimeException('PayKita tidak mengembalikan QRIS pembayaran yang valid.');
            if ((string) ($data['reference'] ?? '') !== $orderId) throw new RuntimeException('Referensi PayKita tidak cocok dengan invoice isi saldo.');
            if (isset($data['base_amount']) && (int) $data['base_amount'] !== $amount) throw new RuntimeException('Nominal PayKita tidak cocok dengan invoice isi saldo.');

            $remoteStatus = strtolower((string) ($data['status'] ?? 'pending'));
            $topup->update([
                'gateway' => 'paykita',
                'provider_reference' => (string) $data['id'],
                'fee' => (int) ($data['fee_amount'] ?? 0) + (int) ($data['unique_code'] ?? 0),
                'total_payment' => (int) ($data['pay_amount'] ?? $amount),
                'payment_method' => 'qris',
                'payment_number' => (string) $data['qris'],
                'checkout_url' => null,
                'status' => $remoteStatus,
                'provider_payload' => $data,
                'expires_at' => $this->date($data['expires_at'] ?? null),
            ]);
            if ($remoteStatus === 'paid') return $this->verify($topup->refresh(), true);
            return $topup->refresh();
        } catch (Throwable $e) {
            $topup->update(['status' => 'failed', 'provider_payload' => ['error' => $e->getMessage()]]);
            throw $e;
        }
    }

    public function verify(Topup $topup, bool $force = false): Topup
    {
        $topup = $force ? $topup->refresh() : $this->normalizeStatus($topup);
        if ($topup->credited_at) return $topup;
        if (! $force && in_array($topup->status, ['completed', 'expired', 'cancelled', 'failed'], true)) return $topup;
        if (blank($topup->provider_reference)) return $topup;

        $response = $this->paykita->order((string) $topup->provider_reference);
        $data = $response['data'] ?? null;
        if (! is_array($data)) throw new RuntimeException('Status PayKita tidak valid.');

        if ((string) ($data['id'] ?? '') !== (string) $topup->provider_reference || (string) ($data['reference'] ?? '') !== $topup->order_id) {
            throw new RuntimeException('Transaksi PayKita tidak cocok dengan invoice lokal.');
        }
        if (isset($data['base_amount']) && (int) $data['base_amount'] !== (int) round((float) $topup->amount)) {
            throw new RuntimeException('Nominal transaksi PayKita tidak cocok dengan invoice lokal.');
        }

        $status = strtolower((string) ($data['status'] ?? 'pending'));
        if ($status === 'paid') {
            $this->creditVerifiedTopup($topup, $data);
        } elseif (in_array($status, ['expired', 'cancelled'], true)) {
            $topup->update(['status' => $status, 'provider_payload' => $data]);
        } else {
            $topup->update([
                'status' => 'pending',
                'fee' => (int) ($data['fee_amount'] ?? 0) + (int) ($data['unique_code'] ?? 0),
                'total_payment' => (int) ($data['pay_amount'] ?? $topup->total_payment),
                'payment_number' => $data['qris'] ?? $topup->payment_number,
                'expires_at' => $this->date($data['expires_at'] ?? null) ?: $topup->expires_at,
                'provider_payload' => $data,
            ]);
        }
        return $topup->refresh();
    }

    public function normalizeStatus(Topup $topup): Topup
    {
        $topup = $topup->refresh();
        if (in_array($topup->status, ['creating', 'pending'], true) && $topup->expires_at?->isPast()) {
            try { return $this->verify($topup, true); } catch (Throwable) { $topup->update(['status' => 'expired']); }
        }
        return $topup->refresh();
    }

    public function expireStale(): int
    {
        $count = 0;
        Topup::query()->whereIn('status', ['creating', 'pending'])->where('expires_at', '<=', now())->limit(200)->get()->each(function (Topup $topup) use (&$count): void {
            if ($this->normalizeStatus($topup)->status === 'expired') $count++;
        });
        return $count;
    }

    public function cancel(Topup $topup, string $reason, ?string $note = null): Topup
    {
        $topup = $this->normalizeStatus($topup);
        if ($topup->status === 'cancelled') return $topup;
        if ($topup->status !== 'pending' || blank($topup->provider_reference)) throw new RuntimeException('Invoice ini sudah tidak dapat dibatalkan.');

        $this->paykita->cancel((string) $topup->provider_reference);
        $topup->update(['status' => 'cancelled', 'cancel_reason' => $reason, 'cancel_note' => $note, 'cancelled_at' => now()]);
        return $topup->refresh();
    }

    private function creditVerifiedTopup(Topup $topup, array $data): void
    {
        DB::transaction(function () use ($topup, $data): void {
            /** @var Topup $locked */
            $locked = Topup::query()->with('user')->lockForUpdate()->findOrFail($topup->id);
            if ($locked->credited_at) return;

            $this->wallet->credit(
                $locked->user,
                (float) $locked->amount,
                'topup',
                'topup-credit:'.$locked->id,
                'Isi saldo '.$locked->order_id,
                Topup::class,
                $locked->id,
                ['gateway' => 'paykita', 'provider_reference' => $locked->provider_reference],
            );

            $locked->update([
                'status' => 'completed',
                'paid_at' => $this->date($data['paid_at'] ?? null) ?: now(),
                'credited_at' => now(),
                'fee' => (int) ($data['fee_amount'] ?? 0) + (int) ($data['unique_code'] ?? 0),
                'total_payment' => (int) ($data['pay_amount'] ?? $locked->total_payment),
                'provider_payload' => $data,
            ]);
        }, 3);
    }

    private function date(mixed $value): mixed
    {
        if (! $value) return null;
        try { return Carbon::parse($value)->setTimezone((string) config('app.timezone', 'Asia/Makassar')); } catch (Throwable) { return null; }
    }
}
