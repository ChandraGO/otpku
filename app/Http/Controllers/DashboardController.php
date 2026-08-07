<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\WalletTransaction;
use App\Services\ProviderBalanceService;
use App\Support\CatalogSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProviderBalanceService $providerBalance): View
    {
        $user = $request->user();
        $user->refresh();

        $adminProviderBalance = $user->isAdmin()
            ? $providerBalance->get(refresh: true)
            : null;

        return view('user.dashboard', [
            'user' => $user,
            'dashboardBalance' => $user->isAdmin()
                ? $adminProviderBalance['idr']
                : (float) $user->balance,
            'dashboardBalanceAvailable' => $user->isAdmin()
                ? (bool) $adminProviderBalance['available']
                : true,
            'dashboardBalanceLabel' => $user->isAdmin() ? 'Saldo provider' : 'Saldo tersedia',
            'dashboardBalanceSource' => $user->isAdmin() ? $adminProviderBalance['source'] : 'wallet',
            'dashboardBalanceCheckedAt' => $user->isAdmin() ? $adminProviderBalance['checked_at'] : null,
            'dashboardBalanceError' => $user->isAdmin() ? $adminProviderBalance['error'] : null,
            'announcements' => Cache::remember(
                'announcements:user-dashboard:v2',
                now()->addMinute(),
                fn () => Announcement::visible()
                    ->orderByDesc('is_pinned')
                    ->latest()
                    ->limit(5)
                    ->get(),
            ),
            'activeOrders' => OtpOrder::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', [
                    'completed',
                    'cancelled',
                    'expired',
                    'refunded',
                    'failed',
                ])
                ->latest()
                ->limit(5)
                ->get(),
            'recentTransactions' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(8)
                ->get(),
            'pendingTopups' => Topup::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->latest()
                ->limit(3)
                ->get(),
            'featuredServices' => CatalogSummary::query()
                ->orderByDesc('catalog_price_stats.total_stock')
                ->limit(8)
                ->get(),
        ]);
    }
}
