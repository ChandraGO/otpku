<?php

namespace App\Observers;

use App\Models\OtpOrder;
use App\Services\ActivityLogger;

class OtpOrderObserver
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function created(OtpOrder $order): void
    {
        $order->loadMissing('user');
        $this->activity->orderCreated($order);
    }

    public function updated(OtpOrder $order): void
    {
        if (! $order->wasChanged('status')) return;
        $order->loadMissing('user');
        $this->activity->orderStatusChanged($order, (string) $order->getOriginal('status'), (string) $order->status);
    }
}
