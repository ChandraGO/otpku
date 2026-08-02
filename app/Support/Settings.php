<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Settings
{
    private const CACHE_KEY = 'kodeotp:settings:all';

    public function get(string $key, mixed $default = null): mixed
    {
        $defaults = config('otp.defaults', []);
        $fallback = array_key_exists($key, $defaults) ? $defaults[$key] : $default;

        try {
            $row = $this->all()[$key] ?? null;
            if (! $row) return $fallback;
            $raw = $row['encrypted'] ? Crypt::decryptString($row['value']) : $row['value'];
            return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $fallback;
        }
    }

    public function set(string $key, mixed $value, ?string $group = null, ?bool $encrypted = null): void
    {
        $encrypted ??= in_array($key, config('otp.secret_keys', []), true);
        $group ??= str($key)->before('.')->toString();
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stored = $encrypted ? Crypt::encryptString($encoded) : $encoded;

        Setting::query()->updateOrCreate(['key' => $key], [
            'group' => $group,
            'value' => $stored,
            'encrypted' => $encrypted,
        ]);
        Cache::forget(self::CACHE_KEY);
    }

    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) $this->set((string) $key, $value);
    }

    public function group(string $group, bool $maskSecrets = true): array
    {
        $result = [];
        foreach (array_keys(config('otp.defaults', [])) as $key) {
            if (! str_starts_with($key, $group.'.')) continue;
            $result[$key] = $maskSecrets && in_array($key, config('otp.secret_keys', []), true)
                ? (filled($this->get($key)) ? '••••••••' : '')
                : $this->get($key);
        }
        return $result;
    }

    private function all(): array
    {
        if (! Schema::hasTable('settings')) return [];
        return Cache::remember(self::CACHE_KEY, 300, fn () => Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
            $s->key => ['value' => $s->value, 'encrypted' => $s->encrypted],
        ])->all());
    }
}
