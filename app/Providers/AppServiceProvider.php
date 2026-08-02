<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Services\MailSettingsConfigurator;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
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
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('login')).'|'.$request->ip()));
        RateLimiter::for('email-otp', fn (Request $request) => Limit::perMinute(3)->by(strtolower((string) ($request->user()?->email ?? $request->input('email'))).'|'.$request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('topups', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip())));

        app(MailSettingsConfigurator::class)->configure();
        View::composer('*', function ($view) use ($settings): void {
            $view->with('siteName', $settings->get('site.name', config('app.name')));
            $view->with('siteDescription', $settings->get('site.description'));
        });
        View::composer('layouts.app', function ($view): void {
            try { $view->with('navAnnouncements', Schema::hasTable('announcements') ? Announcement::visible()->latest()->limit(3)->get() : collect()); }
            catch (Throwable) { $view->with('navAnnouncements', collect()); }
        });
    }

}
