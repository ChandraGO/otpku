<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use Throwable;

class Topup extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['checkout_url', 'provider_payload'];

    protected $fillable = [
        'id',
        'user_id',
        'order_id',
        'amount',
        'fee',
        'total_payment',
        'payment_method',
        'payment_number',
        'checkout_url',
        'status',
        'provider_payload',
        'expires_at',
        'paid_at',
        'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'total_payment' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    /**
     * Supports both newly encrypted values and legacy plaintext rows.
     * A row encrypted with a different APP_KEY is treated as unavailable
     * instead of crashing the invoice page with a 500 response.
     */
    protected function paymentNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                $plain = $this->decryptLegacyString($value);

                return is_string($plain) && $this->containsPakasirCheckoutUrl($plain)
                    ? null
                    : $plain;
            },
            set: fn (mixed $value): ?string => blank($value)
                ? null
                : Crypt::encryptString((string) $value),
        );
    }

    protected function providerPayload(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?array {
                $plain = $this->decryptLegacyString($value);

                if ($plain === null || $plain === '') {
                    return null;
                }

                try {
                    $decoded = json_decode($plain, true, flags: JSON_THROW_ON_ERROR);

                    return is_array($decoded) ? $decoded : ['data' => $decoded];
                } catch (JsonException) {
                    return ['message' => $plain];
                }
            },
            set: function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                $json = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
                );

                return Crypt::encryptString($json);
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private function decryptLegacyString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable $exception) {
            // Do not render a Laravel encrypted payload as a QR/VA number when
            // APP_KEY changed. Plain legacy values are still accepted.
            if ($this->looksLikeLaravelCiphertext($value)) {
                report($exception);

                return null;
            }

            return $value;
        }
    }

    private function containsPakasirCheckoutUrl(string $value): bool
    {
        $normalized = rawurldecode(str_replace('\\/', '/', $value));

        return preg_match(
            '~https?://(?:[^/\s]+\.)?pakasir\.com/pay(?:/|\?|$)~i',
            $normalized,
        ) === 1;
    }

    private function looksLikeLaravelCiphertext(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
