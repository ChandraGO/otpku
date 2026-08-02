<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\SmsCountry;
use App\Models\SmsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'announcements' => Announcement::visible()
                ->where('is_pinned', true)
                ->latest()
                ->limit(3)
                ->get(),
            'featuredServices' => $this->serviceSummaryQuery()
                ->orderByDesc('total_stock')
                ->limit(8)
                ->get(),
            'serviceCount' => SmsService::query()->where('is_active', true)->count(),
            'countryCount' => SmsCountry::query()->where('is_active', true)->count(),
        ]);
    }

    public function pricing(Request $request): View
    {
        $query = $this->serviceSummaryQuery();

        if ($request->filled('q')) {
            $query->where('name', 'ilike', '%'.$request->string('q')->trim().'%');
        }

        match ($request->string('sort', 'popular')->toString()) {
            'price_asc' => $query->orderBy('lowest_price'),
            'price_desc' => $query->orderByDesc('highest_price'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('total_stock')->orderBy('name'),
        };

        return view('pricing', [
            'services' => $query->paginate(30)->withQueryString(),
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

    private function serviceSummaryQuery(): Builder
    {
        $available = fn (Builder $builder) => $builder
            ->where('is_active', true)
            ->where('stock', '>', 0);

        return SmsService::query()
            ->where('is_active', true)
            ->whereHas('prices', $available)
            ->withMin(['prices as lowest_price' => $available], 'sell_price')
            ->withMax(['prices as highest_price' => $available], 'sell_price')
            ->withSum(['prices as total_stock' => $available], 'stock')
            ->withCount(['prices as available_variants' => $available]);
    }
}
