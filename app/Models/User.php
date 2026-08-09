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
        $plain = 'dapetotp_'.Str::random(48);

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
            return;
        }

        // Migrasikan prefix lama secara transparan. Middleware tetap menerima
        // kedua prefix untuk kompatibilitas integrasi yang belum diperbarui.
        $plain = (string) $this->api_key;
        if (str_starts_with($plain, 'otp_live_')) {
            $migrated = 'dapetotp_'.substr($plain, strlen('otp_live_'));
            $this->forceFill([
                'api_key' => $migrated,
                'api_key_hash' => hash('sha256', $migrated),
                'api_key_created_at' => $this->api_key_created_at ?: now(),
            ])->save();
        }
    }

    /**
     * Avatar akun tanpa menyimpan file baru.
     * GitHub dipakai bila akun terhubung; selain itu Gravatar berdasarkan email.
     * Gravatar memakai d=404 sehingga tampilan dapat menghilangkan avatar bila kosong.
     */
    public function emailAvatarUrl(int $size = 96): ?string
    {
        $size = max(32, min(256, $size));

        if (filled($this->github_id) && ctype_digit((string) $this->github_id)) {
            return 'https://avatars.githubusercontent.com/u/'.rawurlencode((string) $this->github_id).'?s='.$size.'&v=4';
        }

        $email = strtolower(trim((string) $this->email));
        if ($email === '') {
            return null;
        }

        return 'https://www.gravatar.com/avatar/'.md5($email).'?s='.$size.'&d=404&r=g';
    }

    public function otpOrders(): HasMany { return $this->hasMany(OtpOrder::class); }
    public function topups(): HasMany { return $this->hasMany(Topup::class); }
    public function walletTransactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function defaultCountry(): BelongsTo { return $this->belongsTo(SmsCountry::class, 'default_country_id'); }
}
