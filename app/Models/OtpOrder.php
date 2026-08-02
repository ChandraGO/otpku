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
    protected $fillable = ['id', 'user_id', 'sms_service_price_id', 'idempotency_key', 'provider_activation_id', 'provider_order_id', 'provider_price_id', 'provider_operator_id', 'service_name', 'country_name', 'operator_name', 'phone_number', 'otp_code', 'provider_cost', 'sell_price', 'status', 'provider_order_status', 'provider_activation_status', 'provider_message', 'provider_payload', 'expires_at', 'otp_received_at', 'completed_at', 'refunded_at', 'last_synced_at'];
    protected function casts(): array
    {
        return [
            'otp_code' => 'encrypted',
            'provider_payload' => 'encrypted:array',
            'provider_cost' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'expires_at' => 'datetime',
            'otp_received_at' => 'datetime',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function price(): BelongsTo { return $this->belongsTo(SmsServicePrice::class, 'sms_service_price_id'); }
    public function isTerminal(): bool { return in_array($this->status, ['completed', 'cancelled', 'expired', 'refunded', 'failed'], true); }
    public function shouldPoll(): bool
    {
        if (! $this->provider_activation_id) return false;
        if ($this->status === 'expired') return ! $this->refunded_at && $this->updated_at?->gte(now()->subHours(6));
        return ! $this->isTerminal();
    }
    public function hasOtp(): bool { return filled($this->otp_code); }
}
