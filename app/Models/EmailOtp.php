<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $fillable = ['user_id', 'email', 'purpose', 'code_hash', 'attempts', 'expires_at', 'used_at', 'ip_address'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'used_at' => 'datetime']; }
    public function isUsable(): bool { return $this->used_at === null && $this->expires_at->isFuture() && $this->attempts < 5; }
}
