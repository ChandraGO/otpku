<?php
namespace App\Jobs;

use App\Models\OtpOrder;
use App\Services\OtpOrderStatusService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOtpOrder implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $timeout = 60;
    public int $tries = 3;
    public int $uniqueFor = 20;
    public function __construct(public readonly string $orderId) {}
    public function uniqueId(): string { return $this->orderId; }
    public function handle(OtpOrderStatusService $service): void
    {
        $order = OtpOrder::query()->find($this->orderId);
        if ($order?->shouldPoll()) $service->sync($order);
    }
}
