<?php
namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Models\SmsServicePrice;
use Illuminate\Http\Response;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'announcements' => Announcement::visible()->where('is_pinned', true)->latest()->limit(3)->get(),
            'popularPrices' => SmsServicePrice::query()->with(['service', 'country'])->where('is_active', true)->where('stock', '>', 0)->orderByDesc('stock')->limit(8)->get(),
            'serviceCount' => SmsService::query()->where('is_active', true)->count(),
            'countryCount' => SmsCountry::query()->where('is_active', true)->count(),
        ]);
    }
    public function pricing(): View
    {
        return view('pricing', ['prices' => SmsServicePrice::query()->with(['service', 'country'])->where('is_active', true)->orderBy('sell_price')->paginate(24)]);
    }
    public function sitemap(): Response
    {
        $xml = view('sitemap')->render();
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
    public function health(): array { return ['ok' => true, 'service' => 'kodeotp', 'time' => now()->toIso8601String()]; }
}
