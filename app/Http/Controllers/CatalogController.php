<?php
namespace App\Http\Controllers;

use App\Models\SmsCountry;
use App\Models\SmsServicePrice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $query = SmsServicePrice::query()->with(['service', 'country'])->where('is_active', true);
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim().'%';
            $query->whereHas('service', fn ($q) => $q->where('name', 'ilike', $term));
        }
        if ($request->filled('country')) $query->where('sms_country_id', $request->integer('country'));
        if ($request->boolean('stock')) $query->where('stock', '>', 0);
        match ($request->string('sort')->toString()) {
            'price_desc' => $query->orderByDesc('sell_price'),
            'stock' => $query->orderByDesc('stock'),
            'success' => $query->orderByDesc('success_rate'),
            default => $query->orderBy('sell_price'),
        };
        return view('user.services', [
            'prices' => $query->paginate(24)->withQueryString(),
            'countries' => SmsCountry::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
