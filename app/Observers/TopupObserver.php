<?php

namespace App\Observers;

use App\Models\Topup;
use App\Services\ActivityLogger;

class TopupObserver
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function created(Topup $topup): void
    {
        $topup->loadMissing('user');
        $this->activity->topupCreated($topup);
    }

    public function updated(Topup $topup): void
    {
        if (! $topup->wasChanged('status')) return;
        $topup->loadMissing('user');
        $this->activity->topupStatusChanged($topup, (string) $topup->getOriginal('status'), (string) $topup->status);
    }
}
