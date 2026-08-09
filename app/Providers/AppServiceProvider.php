<?php

namespace App\Providers;

use App\Models\OtpOrder;
use App\Models\Topup;
use App\Observers\OtpOrderObserver;
use App\Observers\TopupObserver;
use App\Services\MailSettingsConfigurator;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
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

        Topup::observe(TopupObserver::class);
        OtpOrder::observe(OtpOrderObserver::class);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('login')).'|'.$request->ip()));
        RateLimiter::for('email-otp', fn (Request $request) => Limit::perMinute(3)
            ->by(strtolower((string) ($request->user()?->email ?? $request->input('email'))).'|'.$request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('topups', fn (Request $request) => Limit::perMinute(5)
            ->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('customer-api', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user()?->id ?? $request->ip())));

        app(MailSettingsConfigurator::class)->configure();

        View::composer('*', function ($view) use ($settings): void {
            try {
                $view->with('siteName', $settings->get('site.name', config('app.name')));
                $view->with('siteDescription', $settings->get('site.description'));
                $view->with('siteSupportWhatsapp', $settings->get('site.support_whatsapp', ''));
                $view->with('siteLogoUrl', $settings->get('site.logo_url', ''));
                $view->with('siteLogoZoom', max(100, min(400, (int) $settings->get('site.logo_zoom', 240))));
                $view->with('siteLogoMobileShift', max(-5, min(30, (int) $settings->get('site.logo_mobile_shift', 0))));
                $view->with('siteSeoTitle', $settings->get('site.seo_title', ''));
                $view->with('siteSeoDescription', $settings->get('site.seo_description', ''));
                $view->with('siteSeoKeywords', $settings->get('site.seo_keywords', ''));
                $view->with('siteSeoHashtags', $settings->get('site.seo_hashtags', ''));
                $view->with('siteSeoImageUrl', $settings->get('site.seo_image_url', ''));
            } catch (Throwable) {
                $view->with('siteName', config('app.name', 'KodeOTP'));
                $view->with('siteDescription', null);
                $view->with('siteSupportWhatsapp', '');
                $view->with('siteLogoUrl', '');
                $view->with('siteLogoZoom', 240);
                $view->with('siteLogoMobileShift', 0);
                $view->with('siteSeoTitle', '');
                $view->with('siteSeoDescription', '');
                $view->with('siteSeoKeywords', '');
                $view->with('siteSeoHashtags', '');
                $view->with('siteSeoImageUrl', '');
            }
        });

        View::composer('layouts.app', function ($view) use ($settings): void {
            // Tidak ada query pengumuman global di layout; dashboard/list memuatnya hanya saat dibutuhkan.
            $user = request()->user();
            $data = [
                'headerBalanceLabel' => 'Saldo',
                'headerBalance' => (float) ($user?->balance ?? 0),
                'headerBalanceAvailable' => true,
                'headerTopupLabel' => 'Isi Saldo',
                'headerTopupUrl' => route('topups.index'),
                'headerTopupExternal' => false,
            ];

            if ($user?->isAdmin()) {
                $data['headerBalanceLabel'] = 'Saldo provider';
                $data['headerTopupLabel'] = 'Isi Saldo Penyedia';
                $data['headerTopupUrl'] = 'https://sms-virtual.net';
                $data['headerTopupExternal'] = true;
                $data['headerBalanceAvailable'] = false;

                // Database-only. Tidak menyentuh Redis atau API provider saat render.
                try {
                    $raw = $settings->get('sms_virtual.last_balance_raw');
                    if (is_numeric($raw)) {
                        $data['headerBalance'] = (float) $raw;
                        $data['headerBalanceAvailable'] = true;
                    }
                } catch (Throwable) {
                    $data['headerBalanceAvailable'] = false;
                }
            }

            $view->with($data);
        });
    }
}
