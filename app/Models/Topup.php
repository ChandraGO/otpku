<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topup extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id', 'order_id', 'amount', 'fee', 'total_payment', 'payment_method', 'payment_number', 'checkout_url', 'status', 'provider_payload', 'expires_at', 'paid_at', 'credited_at'];
    protected function casts(): array { return ['payment_number' => 'encrypted', 'provider_payload' => 'encrypted:array', 'amount' => 'decimal:2', 'fee' => 'decimal:2', 'total_payment' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'credited_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
