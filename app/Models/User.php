<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username', 'name', 'whatsapp', 'email', 'password', 'role', 'status', 'balance', 'theme', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:2',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isActive(): bool { return $this->status === 'active'; }

    public function otpOrders(): HasMany { return $this->hasMany(OtpOrder::class); }
    public function topups(): HasMany { return $this->hasMany(Topup::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
}
