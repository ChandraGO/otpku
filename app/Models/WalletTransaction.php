<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id', 'reference_key', 'direction', 'category', 'amount', 'balance_before', 'balance_after', 'reference_type', 'reference_id', 'description', 'meta'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2', 'meta' => 'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
