<?php

namespace App\Observers;

use App\Models\Topup;
use App\Services\ActivityLogger;
use App\Services\PaymentGatewayManager;

class TopupObserver
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function created(Topup $topup): void
    {
        $topup->loadMissing('user');
        $this->activity->topupCreated($topup);
    }

    public function updated(Topup $topup): void
    {
        if (! $topup->wasChanged('status')) {
            return;
        }

        $topup->loadMissing('user');
        $from = (string) $topup->getOriginal('status');
        $to = (string) $topup->status;
        $this->activity->topupStatusChanged($topup, $from, $to);

        if (! in_array($to, ['creating', 'pending'], true)) {
            $this->gateways->applyPendingIfSafe();
        }
    }
}
