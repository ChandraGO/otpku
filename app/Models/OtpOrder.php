<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpOrder extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id', 'sms_service_price_id', 'idempotency_key', 'provider_activation_id', 'provider_order_id', 'provider_price_id', 'provider_operator_id', 'service_name', 'country_name', 'operator_name', 'phone_number', 'otp_code', 'provider_cost', 'sell_price', 'payment_channel', 'payment_status', 'paykita_order_id', 'payment_base_amount', 'payment_fee_amount', 'payment_unique_code', 'payment_pay_amount', 'payment_qris', 'payment_checkout_url', 'payment_payload', 'payment_expires_at', 'payment_paid_at', 'status', 'provider_order_status', 'provider_activation_status', 'provider_message', 'provider_payload', 'expires_at', 'otp_received_at', 'completed_at', 'refunded_at', 'last_synced_at'];
    protected function casts(): array
    {
        return [
            'otp_code' => 'encrypted',
            'provider_payload' => 'encrypted:array',
            'provider_cost' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'payment_base_amount' => 'decimal:2',
            'payment_fee_amount' => 'decimal:2',
            'payment_pay_amount' => 'decimal:2',
            'payment_payload' => 'encrypted:array',
            'payment_expires_at' => 'datetime',
            'payment_paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'otp_received_at' => 'datetime',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function price(): BelongsTo { return $this->belongsTo(SmsServicePrice::class, 'sms_service_price_id'); }
    public function isTerminal(): bool { return in_array($this->status, ['completed', 'cancelled', 'expired', 'refunded', 'failed', 'payment_failed'], true); }
    public function shouldPoll(): bool
    {
        if (! $this->provider_activation_id) return false;
        if ($this->status === 'expired') return ! $this->refunded_at && $this->updated_at?->gte(now()->subHours(6));
        return ! $this->isTerminal();
    }
    public function hasOtp(): bool { return filled($this->otp_code); }
}
