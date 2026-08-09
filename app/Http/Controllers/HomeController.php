<?php

namespace App\Http\Controllers;

use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Models\User;
use App\Support\CatalogSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $publicCounts = Cache::remember(
            'catalog:public-counts:v4',
            now()->addMinute(),
            fn (): array => [
                'services' => SmsService::query()
                    ->where('is_active', true)
                    ->count(),
                'countries' => SmsCountry::query()
                    ->where('is_active', true)
                    ->count(),
                // Baseline tampilan pengguna dimulai dari 50, lalu ditambah jumlah akun aktual.
                'users' => 50 + User::query()->count(),
            ],
        );

        $otpPreviewServices = Cache::remember(
            'catalog:home-otp-preview-services:v1',
            now()->addMinutes(15),
            function (): array {
                $preferred = ['whatsapp', 'telegram', 'google'];

                $services = SmsService::query()
                    ->where('is_active', true)
                    ->whereNotNull('icon_url')
                    ->where('icon_url', '<>', '')
                    ->where(function ($query) use ($preferred): void {
                        foreach ($preferred as $needle) {
                            $query->orWhereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']);
                        }
                    })
                    ->get(['id', 'name', 'icon_url']);

                $ordered = collect($preferred)
                    ->map(fn (string $needle) => $services->first(
                        fn (SmsService $service): bool => str_contains(
                            mb_strtolower($service->name),
                            $needle,
                        ),
                    ))
                    ->filter()
                    ->values();

                if ($ordered->count() < 3) {
                    $fallback = SmsService::query()
                        ->where('is_active', true)
                        ->whereNotNull('icon_url')
                        ->where('icon_url', '<>', '')
                        ->when(
                            $ordered->isNotEmpty(),
                            fn ($query) => $query->whereNotIn('id', $ordered->pluck('id')),
                        )
                        ->orderBy('name')
                        ->limit(3 - $ordered->count())
                        ->get(['id', 'name', 'icon_url']);

                    $ordered = $ordered->concat($fallback)->values();
                }

                return $ordered
                    ->unique('id')
                    ->take(3)
                    ->map(fn (SmsService $service): array => [
                        'name' => $service->name,
                        'icon_url' => $service->icon_url,
                    ])
                    ->values()
                    ->all();
            },
        );

        return view('home', [
            'featuredServices' => CatalogSummary::query()
                ->orderByDesc('catalog_price_stats.total_stock')
                ->limit(8)
                ->get(),
            'serviceCount' => $publicCounts['services'],
            'countryCount' => $publicCounts['countries'],
            'userCount' => $publicCounts['users'],
            'otpPreviewServices' => $otpPreviewServices,
        ]);
    }

    public function pricing(Request $request): View
    {
        $query = CatalogSummary::query();

        if ($request->filled('q')) {
            $query->where(
                'sms_services.name',
                'ilike',
                '%'.$request->string('q')->trim().'%',
            );
        }

        match ($request->string('sort', 'popular')->toString()) {
            'price_asc' => $query->orderBy('catalog_price_stats.lowest_price'),
            'price_desc' => $query->orderByDesc('catalog_price_stats.highest_price'),
            'name' => $query->orderBy('sms_services.name'),
            default => $query
                ->orderByDesc('catalog_price_stats.total_stock')
                ->orderBy('sms_services.name'),
        };

        $services = $query->simplePaginate(30)->withQueryString();

        if ($request->ajax() || $request->boolean('partial')) {
            return view('partials.pricing-results', compact('services'));
        }

        return view('pricing', compact('services'));
    }

    public function sitemap(): Response
    {
        return response(view('sitemap')->render(), 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function health(): array
    {
        return [
            'ok' => true,
            'service' => 'kodeotp',
            'time' => now()->toIso8601String(),
        ];
    }
}
