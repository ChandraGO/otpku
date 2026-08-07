<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'whatsapp',
        'telegram_id',
        'default_country_id',
        'email',
        'github_id',
        'password',
        'role',
        'status',
        'balance',
        'theme',
        'email_verified_at',
        'last_login_at',
        'deletion_requested_at',
        'deletion_request_reason',
        'deletion_request_status',
        'deletion_reviewed_at',
    ];

    protected $hidden = ['password', 'remember_token', 'api_key', 'api_key_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'api_key' => 'encrypted',
            'api_key_created_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'deletion_reviewed_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:2',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isActive(): bool { return $this->status === 'active'; }

    public function rotateApiKey(): string
    {
        $plain = 'otp_live_'.Str::random(48);

        $this->forceFill([
            'api_key' => $plain,
            'api_key_hash' => hash('sha256', $plain),
            'api_key_created_at' => now(),
        ])->save();

        return $plain;
    }

    public function ensureApiKey(): void
    {
        if (! filled($this->api_key_hash) || ! filled($this->api_key)) {
            $this->rotateApiKey();
        }
    }

    public function apiKeyMasked(): ?string
    {
        if (! filled($this->api_key)) return null;

        $key = (string) $this->api_key;
        return Str::substr($key, 0, 10).'••••••••••••••••••••••••'.Str::substr($key, -4);
    }

    public function otpOrders(): HasMany { return $this->hasMany(OtpOrder::class); }
    public function topups(): HasMany { return $this->hasMany(Topup::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function defaultCountry(): BelongsTo { return $this->belongsTo(SmsCountry::class, 'default_country_id'); }
}
