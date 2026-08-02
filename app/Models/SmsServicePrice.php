<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsServicePrice extends Model
{
    protected $fillable = ['sms_country_id', 'sms_service_id', 'provider_price_id', 'provider_operator_id', 'operator_name', 'provider_price', 'sell_price', 'stock', 'success_rate', 'is_active', 'provider_payload', 'synced_at'];
    protected function casts(): array { return ['provider_price' => 'decimal:2', 'sell_price' => 'decimal:2', 'success_rate' => 'decimal:2', 'stock' => 'integer', 'is_active' => 'boolean', 'provider_payload' => 'array', 'synced_at' => 'datetime']; }
    public function country(): BelongsTo { return $this->belongsTo(SmsCountry::class, 'sms_country_id'); }
    public function service(): BelongsTo { return $this->belongsTo(SmsService::class, 'sms_service_id'); }
}
