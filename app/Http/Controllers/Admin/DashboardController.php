<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SmsVirtualClient;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(SmsVirtualClient $client): View
    {
        $providerBalance = null; $providerError = null;
        try { $response = $client->balance(); $providerBalance = $response['balance'] ?? $response['data']['balance'] ?? $response['data'] ?? null; }
        catch (Throwable $e) { $providerError = $e->getMessage(); }
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->where('role', 'user')->count(),
                'user_balance' => (float) User::query()->sum('balance'),
                'completed_topups' => (float) Topup::query()->where('status', 'completed')->sum('amount'),
                'orders_today' => OtpOrder::query()->whereDate('created_at', today())->count(),
                'revenue_today' => (float) OtpOrder::query()->whereDate('created_at', today())->whereNotIn('status', ['failed', 'refunded'])->sum('sell_price'),
                'profit_today' => (float) OtpOrder::query()->whereDate('created_at', today())->whereNotIn('status', ['failed', 'refunded'])->selectRaw('COALESCE(SUM(sell_price - provider_cost), 0) total')->value('total'),
            ],
            'providerBalance' => $providerBalance,
            'providerError' => $providerError,
            'recentOrders' => OtpOrder::query()->with('user')->latest()->limit(8)->get(),
            'recentTopups' => Topup::query()->with('user')->latest()->limit(8)->get(),
        ]);
    }
}
