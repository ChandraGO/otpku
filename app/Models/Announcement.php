<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['created_by', 'title', 'body', 'type', 'is_active', 'is_pinned', 'starts_at', 'ends_at'];
    protected function casts(): array { return ['is_active' => 'boolean', 'is_pinned' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
