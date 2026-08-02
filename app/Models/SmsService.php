<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsService extends Model
{
    protected $fillable = ['provider_id', 'name', 'slug', 'icon_url', 'min_provider_price', 'max_provider_price', 'is_active', 'provider_payload', 'synced_at'];
    protected function casts(): array { return ['is_active' => 'boolean', 'provider_payload' => 'array', 'synced_at' => 'datetime', 'min_provider_price' => 'decimal:2', 'max_provider_price' => 'decimal:2']; }
    public function prices(): HasMany { return $this->hasMany(SmsServicePrice::class); }
}
