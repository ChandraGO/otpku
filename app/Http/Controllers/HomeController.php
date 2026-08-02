<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Support\CatalogSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $catalogCounts = Cache::remember(
            'catalog:public-counts:v2',
            now()->addMinute(),
            fn (): array => [
                'services' => SmsService::query()
                    ->where('is_active', true)
                    ->count(),
                'countries' => SmsCountry::query()
                    ->where('is_active', true)
                    ->count(),
            ],
        );

        return view('home', [
            'announcements' => Cache::remember(
                'announcements:home:v2',
                now()->addMinute(),
                fn () => Announcement::visible()
                    ->where('is_pinned', true)
                    ->latest()
                    ->limit(3)
                    ->get(),
            ),
            'featuredServices' => CatalogSummary::query()
                ->orderByDesc('catalog_price_stats.total_stock')
                ->limit(8)
                ->get(),
            'serviceCount' => $catalogCounts['services'],
            'countryCount' => $catalogCounts['countries'],
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

        return view('pricing', [
            'services' => $query->simplePaginate(30)->withQueryString(),
        ]);
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
