<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Arr;
use Throwable;

class ProviderBalanceService
{
    public function __construct(
        private readonly SmsVirtualClient $client,
        private readonly Settings $settings,
    ) {}

    /**
     * Return the provider balance in the same IDR units used by the app.
     *
     * When $refresh is true we always ask the provider first so an external
     * provider top-up is visible immediately. The last successful value is
     * stored only as a fallback when the provider is temporarily unreachable.
     */
    public function get(bool $refresh = true): array
    {
        // SMS Virtual balance is already in the same monetary unit charged by orders.
        $unitToIdr = 1.0;

        $lastRaw = $this->numeric($this->settings->get('sms_virtual.last_balance_raw'));
        $lastCheckedAt = $this->settings->get('sms_virtual.last_balance_checked_at');

        if ($refresh) {
            try {
                $response = $this->client->balance();
                $raw = $this->extractBalance($response);

                if ($raw === null) {
                    throw new \RuntimeException('Provider tidak mengembalikan nilai saldo yang valid.');
                }

                $checkedAt = now()->toIso8601String();
                $this->settings->setMany([
                    'sms_virtual.last_balance_raw' => $raw,
                    'sms_virtual.last_balance_checked_at' => $checkedAt,
                ]);

                return [
                    'raw' => $raw,
                    'idr' => $raw * $unitToIdr,
                    'unit_to_idr' => $unitToIdr,
                    'checked_at' => $checkedAt,
                    'source' => 'provider',
                    'available' => true,
                    'error' => null,
                ];
            } catch (Throwable $e) {
                report($e);

                if ($lastRaw !== null) {
                    return [
                        'raw' => $lastRaw,
                        'idr' => $lastRaw * $unitToIdr,
                        'unit_to_idr' => $unitToIdr,
                        'checked_at' => $lastCheckedAt,
                        'source' => 'fallback',
                        'available' => true,
                        'error' => 'Saldo live gagal dimuat. Menampilkan saldo terakhir yang berhasil disinkronkan.',
                    ];
                }

                return [
                    'raw' => null,
                    'idr' => null,
                    'unit_to_idr' => $unitToIdr,
                    'checked_at' => $lastCheckedAt,
                    'source' => 'unavailable',
                    'available' => false,
                    'error' => 'Saldo provider belum dapat dimuat: '.$e->getMessage(),
                ];
            }
        }

        return [
            'raw' => $lastRaw,
            'idr' => $lastRaw === null ? null : $lastRaw * $unitToIdr,
            'unit_to_idr' => $unitToIdr,
            'checked_at' => $lastCheckedAt,
            'source' => $lastRaw === null ? 'unavailable' : 'stored',
            'available' => $lastRaw !== null,
            'error' => $lastRaw === null ? 'Saldo provider belum pernah disinkronkan.' : null,
        ];
    }

    private function extractBalance(array $response): ?float
    {
        foreach ([
            'balance',
            'data.balance',
            'data.currentBalance',
            'data.current_balance',
            'data.wallet.balance',
            'wallet.balance',
            'result.balance',
            'result.currentBalance',
        ] as $key) {
            $value = Arr::get($response, $key);
            $number = $this->numeric($value);
            if ($number !== null) {
                return $number;
            }
        }

        // Some provider versions return the numeric balance directly in data.
        return $this->numeric($response['data'] ?? null);
    }

    private function numeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Be permissive for formatted provider values such as "Rp 21.219".
        $normalized = preg_replace('/[^0-9,.-]/', '', $value);
        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
