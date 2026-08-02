<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCountry extends Model
{
    protected $fillable = ['provider_id', 'name', 'iso_code', 'dial_code', 'flag_url', 'is_active', 'provider_payload', 'synced_at'];
    protected function casts(): array { return ['is_active' => 'boolean', 'provider_payload' => 'array', 'synced_at' => 'datetime']; }
    public function prices(): HasMany { return $this->hasMany(SmsServicePrice::class); }
}
