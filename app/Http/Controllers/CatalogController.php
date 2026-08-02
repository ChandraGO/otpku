<?php

namespace App\Http\Controllers;

use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Models\SmsServicePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $priceConstraint = $this->priceConstraint($request);

        $query = SmsService::query()
            ->where('is_active', true)
            ->whereHas('prices', $priceConstraint)
            ->withMin(['prices as lowest_price' => $priceConstraint], 'sell_price')
            ->withMax(['prices as highest_price' => $priceConstraint], 'sell_price')
            ->withSum(['prices as total_stock' => $priceConstraint], 'stock')
            ->withCount(['prices as available_variants' => $priceConstraint]);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->where('name', 'ilike', $term);
        }

        match ($request->string('sort', 'popular')->toString()) {
            'price_asc' => $query->orderBy('lowest_price'),
            'price_desc' => $query->orderByDesc('highest_price'),
            'stock' => $query->orderByDesc('total_stock'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('total_stock')->orderBy('name'),
        };

        return view('user.services', [
            'services' => $query->paginate(24)->withQueryString(),
            'countries' => SmsCountry::query()
                ->where('is_active', true)
                ->whereHas('prices', fn (Builder $builder) => $builder->where('is_active', true))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, SmsService $service): View
    {
        abort_unless($service->is_active, 404);

        $query = SmsServicePrice::query()
            ->with('country')
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
            'country' => $query->join('sms_countries', 'sms_countries.id', '=', 'sms_service_prices.sms_country_id')
                ->orderBy('sms_countries.name')
                ->select('sms_service_prices.*'),
            default => $query->orderBy('sell_price'),
        };

        $summary = SmsServicePrice::query()
            ->where('sms_service_id', $service->id)
            ->where('is_active', true)
            ->selectRaw('MIN(sell_price) AS lowest_price, MAX(sell_price) AS highest_price, COALESCE(SUM(stock), 0) AS total_stock, COUNT(*) AS variants')
            ->first();

        return view('user.service-show', [
            'service' => $service,
            'prices' => $query->paginate(30)->withQueryString(),
            'summary' => $summary,
            'countries' => SmsCountry::query()
                ->where('is_active', true)
                ->whereHas('prices', fn (Builder $builder) => $builder
                    ->where('sms_service_id', $service->id)
                    ->where('is_active', true))
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function priceConstraint(Request $request): callable
    {
        return function (Builder $builder) use ($request): void {
            $builder->where('is_active', true);

            if ($request->filled('country')) {
                $builder->where('sms_country_id', $request->integer('country'));
            }

            if ($request->boolean('stock', true)) {
                $builder->where('stock', '>', 0);
            }
        };
    }
}
