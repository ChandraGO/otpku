<?php

namespace App\Observers;

use App\Models\OtpOrder;
use App\Services\ActivityLogger;
use App\Services\PaymentGatewayManager;

class OtpOrderObserver
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function created(OtpOrder $order): void
    {
        $order->loadMissing('user');
        $this->activity->orderCreated($order);
    }

    public function updated(OtpOrder $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $order->loadMissing('user');
        $from = (string) $order->getOriginal('status');
        $to = (string) $order->status;
        $this->activity->orderStatusChanged($order, $from, $to);

        if ($order->isTerminal()) {
            $this->gateways->applyPendingIfSafe();
        }
    }
}
