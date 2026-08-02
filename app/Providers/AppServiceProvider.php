<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Services\MailSettingsConfigurator;
use App\Services\SmsVirtualClient;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(Settings $settings): void
    {
        Paginator::useTailwind();

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('login')).'|'.$request->ip()));
        RateLimiter::for('email-otp', fn (Request $request) => Limit::perMinute(3)
            ->by(strtolower((string) ($request->user()?->email ?? $request->input('email'))).'|'.$request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('topups', fn (Request $request) => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        app(MailSettingsConfigurator::class)->configure();

        View::composer('*', function ($view) use ($settings): void {
            $view->with('siteName', $settings->get('site.name', config('app.name')));
            $view->with('siteDescription', $settings->get('site.description'));
            $view->with('siteSupportWhatsapp', $settings->get('site.support_whatsapp', ''));
        });

        View::composer('layouts.app', function ($view) use ($settings): void {
            try {
                $view->with(
                    'navAnnouncements',
                    Schema::hasTable('announcements')
                        ? Cache::remember(
                            'announcements:navigation:v2',
                            now()->addMinute(),
                            fn () => Announcement::visible()
                                ->latest()
                                ->limit(3)
                                ->get(),
                        )
                        : collect(),
                );
            } catch (Throwable) {
                $view->with('navAnnouncements', collect());
            }

            $user = request()->user();
            $headerBalanceLabel = 'Balance';
            $headerBalance = (float) ($user?->balance ?? 0);
            $headerBalanceAvailable = true;
            $headerTopupLabel = 'Top Up';
            $headerTopupUrl = route('topups.index');
            $headerTopupExternal = false;

            if ($user?->isAdmin()) {
                $headerBalanceLabel = 'Saldo provider';
                $headerTopupLabel = 'Top Up Provider';
                $headerTopupUrl = 'https://sms-virtual.net';
                $headerTopupExternal = true;

                try {
                    $response = Cache::remember(
                        'sms-virtual:provider-balance:v2',
                        now()->addSeconds(30),
                        fn () => app(SmsVirtualClient::class)->balance(),
                    );
                    $rawBalance = $response['balance']
                        ?? data_get($response, 'data.balance')
                        ?? $response['data']
                        ?? null;

                    if (! is_numeric($rawBalance)) {
                        $headerBalanceAvailable = false;
                    } else {
                        $unitToIdr = max(
                            0.0001,
                            (float) $settings->get(
                                'sms_virtual.balance_unit_to_idr',
                                1,
                            ),
                        );
                        $headerBalance = (float) $rawBalance * $unitToIdr;
                    }
                } catch (Throwable) {
                    $headerBalanceAvailable = false;
                }
            }

            $view->with([
                'headerBalanceLabel' => $headerBalanceLabel,
                'headerBalance' => $headerBalance,
                'headerBalanceAvailable' => $headerBalanceAvailable,
                'headerTopupLabel' => $headerTopupLabel,
                'headerTopupUrl' => $headerTopupUrl,
                'headerTopupExternal' => $headerTopupExternal,
            ]);
        });
    }
}
