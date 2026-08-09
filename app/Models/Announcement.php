<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'body',
        'image_path',
        'type',
        'is_active',
        'is_pinned',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_pinned' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * Serve announcement images through Laravel instead of /storage directly.
     *
     * The upload is stored in the persistent shared public disk. Serving it through
     * a controller avoids depending on a public/storage symlink or nested static
     * bind mount, which was the source of the production 404 after a successful upload.
     */
    public function imageUrl(): ?string
    {
        if (! filled($this->image_path) || ! $this->exists) {
            return null;
        }

        return route('media.announcements.show', [
            'announcement' => $this->getKey(),
            'v' => $this->updated_at?->timestamp ?? $this->created_at?->timestamp ?? 1,
        ]);
    }
}
