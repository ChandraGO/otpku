<?php

use App\Jobs\PlaceOtpOrder;
use App\Jobs\SyncOtpOrder;
use App\Jobs\SyncPendingTopups;
use App\Jobs\SyncSmsVirtualCatalog;
use App\Models\OtpOrder;
use App\Services\BackupService;
use App\Services\CatalogSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('otp:catalog-sync', function (CatalogSyncService $service): void {
    $this->info(json_encode($service->sync(), JSON_PRETTY_PRINT));
})->purpose('Sinkronkan katalog SMS Virtual ke database lokal');

Schedule::call(function (): void {
    OtpOrder::query()->whereIn('status', ['processing', 'provider_pending'])
        ->whereNull('provider_activation_id')->where('created_at', '>=', now()->subDays(7))
        ->orderBy('updated_at')->limit(100)->pluck('id')
        ->each(fn (string $id) => PlaceOtpOrder::dispatch($id));

    OtpOrder::query()->whereNotIn('status', ['completed', 'cancelled', 'refunded', 'failed'])
        ->whereNotNull('provider_activation_id')
        ->where(fn ($query) => $query->where('status', '!=', 'expired')->orWhere('updated_at', '>=', now()->subHours(6)))
        ->orderBy('last_synced_at')->limit(200)->pluck('id')
        ->each(fn (string $id) => SyncOtpOrder::dispatch($id));
})->everyTenSeconds()->name('sync-active-otp-orders')->withoutOverlapping();

Schedule::job(new SyncPendingTopups)->everyMinute()->withoutOverlapping();
Schedule::job(new SyncSmsVirtualCatalog)->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => app(BackupService::class)->create())->dailyAt('03:30')->name('daily-database-backup')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->daily();
