<?php
namespace App\Jobs;

use App\Models\Topup;
use App\Services\TopupService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncPendingTopups implements ShouldQueue, ShouldBeUnique
{
    use Queueable;
    public int $timeout = 300;
    public int $uniqueFor = 55;
    public function handle(TopupService $service): void
    {
        $service->expireStale();

        Topup::query()->where('status', 'pending')
            ->where('gateway', 'paykita')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->subDay()))
            ->orderBy('created_at')->limit(100)->get()->each(function (Topup $topup) use ($service): void {
                try { $service->verify($topup); } catch (Throwable) {}
            });
    }
}
