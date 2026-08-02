<?php

namespace App\Http\Controllers;

use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Models\SmsServicePrice;
use App\Support\CatalogSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $countryId = $request->filled('country')
            ? $request->integer('country')
            : null;
        $stockOnly = $request->boolean('stock', true);
        $query = CatalogSummary::query($countryId, $stockOnly);

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
            'stock' => $query->orderByDesc('catalog_price_stats.total_stock'),
            'name' => $query->orderBy('sms_services.name'),
            default => $query
                ->orderByDesc('catalog_price_stats.total_stock')
                ->orderBy('sms_services.name'),
        };

        return view('user.services', [
            'services' => $query->simplePaginate(24)->withQueryString(),
            'countries' => $this->activeCountries(),
        ]);
    }

    public function show(Request $request, SmsService $service): View
    {
        abort_unless($service->is_active, 404);

        $query = SmsServicePrice::query()
            ->with('country:id,name,iso_code,flag_url')
            ->where('sms_service_id', $service->id)
            ->where('is_active', true);

        if ($request->filled('country')) {
            $query->where('sms_country_id', $request->integer('country'));
        }

        if ($request->boolean('stock', true)) {
            $query->where('stock', '>', 0);
        }

        match ($request->string('sort', 'price_asc')->toString()) {
            'price_desc' => $query->orderByDesc('sell_price'),
            'stock' => $query->orderByDesc('stock'),
            'country' => $query
                ->join(
                    'sms_countries',
                    'sms_countries.id',
                    '=',
                    'sms_service_prices.sms_country_id',
                )
                ->orderBy('sms_countries.name')
                ->select('sms_service_prices.*'),
            default => $query->orderBy('sell_price'),
        };

        $summary = SmsServicePrice::query()
            ->where('sms_service_id', $service->id)
            ->where('is_active', true)
            ->selectRaw(
                'MIN(sell_price) AS lowest_price, '.
                'MAX(sell_price) AS highest_price, '.
                'COALESCE(SUM(stock), 0) AS total_stock, '.
                'COUNT(*) AS variants',
            )
            ->first();

        return view('user.service-show', [
            'service' => $service,
            'prices' => $query->simplePaginate(30)->withQueryString(),
            'summary' => $summary,
            'countries' => SmsCountry::query()
                ->select(['id', 'name', 'iso_code', 'flag_url'])
                ->where('is_active', true)
                ->whereHas(
                    'prices',
                    fn (Builder $builder) => $builder
                        ->where('sms_service_id', $service->id)
                        ->where('is_active', true),
                )
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function activeCountries()
    {
        return Cache::remember(
            'catalog:active-countries:v2',
            now()->addMinute(),
            fn () => SmsCountry::query()
                ->select(['id', 'name', 'iso_code', 'flag_url'])
                ->where('is_active', true)
                ->whereHas(
                    'prices',
                    fn (Builder $builder) => $builder
                        ->where('is_active', true)
                        ->where('stock', '>', 0),
                )
                ->orderBy('name')
                ->get(),
        );
    }
}
