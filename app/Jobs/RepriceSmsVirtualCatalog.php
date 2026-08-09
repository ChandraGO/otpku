<?php

namespace App\Jobs;

use App\Services\CatalogSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RepriceSmsVirtualCatalog implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 2;
    public int $uniqueFor = 120;

    public function handle(CatalogSyncService $service): void
    {
        $service->reprice();
    }
}
