<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\WalletTransaction;
use App\Services\ProviderBalanceService;
use App\Support\CatalogSummary;
use Illuminate\Http\Request;
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

        $loginAnnouncement = null;
        if ($request->session()->pull('show_login_announcement', false)) {
            $welcomeTitle = 'Selamat datang di '.config('app.name', 'KodeOTP');
            $welcomeBody = 'Silakan periksa harga dan stok sebelum melakukan pemesanan. Gunakan nomor hanya untuk aktivitas yang sah dan sesuai ketentuan layanan tujuan.';

            $loginAnnouncement = Announcement::visible()
                ->where('title', $welcomeTitle)
                ->latest()
                ->first();

            if (! $loginAnnouncement) {
                $loginAnnouncement = new Announcement([
                    'title' => $welcomeTitle,
                    'body' => $welcomeBody,
                    'type' => 'info',
                    'is_active' => true,
                ]);
            }
        }

        return view('user.dashboard', [
            'user' => $user,
            'loginAnnouncement' => $loginAnnouncement,
            'dashboardBalance' => $user->isAdmin()
                ? $adminProviderBalance['idr']
                : (float) $user->balance,
            'dashboardBalanceAvailable' => $user->isAdmin()
                ? (bool) $adminProviderBalance['available']
                : true,
            'dashboardBalanceLabel' => $user->isAdmin() ? 'Saldo penyedia' : 'Saldo tersedia',
            'dashboardBalanceSource' => $user->isAdmin() ? $adminProviderBalance['source'] : 'wallet',
            'dashboardBalanceCheckedAt' => $user->isAdmin() ? $adminProviderBalance['checked_at'] : null,
            'dashboardBalanceError' => $user->isAdmin() ? $adminProviderBalance['error'] : null,
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
