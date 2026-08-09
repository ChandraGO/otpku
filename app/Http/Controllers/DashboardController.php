<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\OtpOrder;
use App\Services\ProviderBalanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProviderBalanceService $providerBalance): View
    {
        $user = $request->user();
        $user->refresh();

        // Dashboard harus cepat. Saldo admin memakai nilai provider terakhir yang sudah
        // tersimpan; sinkronisasi live tidak lagi memblokir render halaman.
        $adminProviderBalance = $user->isAdmin()
            ? $providerBalance->get(refresh: false)
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

        $orders = OtpOrder::query()->where('user_id', $user->id);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return view('user.dashboard', [
            'user' => $user,
            'loginAnnouncement' => $loginAnnouncement,
            'dashboardBalance' => $user->isAdmin()
                ? $adminProviderBalance['idr']
                : (float) $user->balance,
            'dashboardBalanceAvailable' => $user->isAdmin()
                ? (bool) $adminProviderBalance['available']
                : true,
            'dashboardBalanceLabel' => $user->isAdmin() ? 'Saldo penyedia' : 'Sisa saldo',
            'dashboardBalanceSource' => $user->isAdmin() ? $adminProviderBalance['source'] : 'wallet',
            'dashboardBalanceCheckedAt' => $user->isAdmin() ? $adminProviderBalance['checked_at'] : null,
            'dashboardBalanceError' => $user->isAdmin() ? $adminProviderBalance['error'] : null,
            'totalOrders' => (clone $orders)->count(),
            'totalSpent' => (float) (clone $orders)
                ->whereNotIn('status', ['cancelled', 'refunded', 'failed'])
                ->sum('sell_price'),
            'topServices' => (clone $orders)
                ->select('service_name')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('service_name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'monthFrequentServices' => (clone $orders)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->select('service_name')
                ->selectRaw('COUNT(*) AS total')
                ->groupBy('service_name')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'monthRecentOrders' => (clone $orders)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->latest()
                ->limit(8)
                ->get(),
            'latestAnnouncements' => Announcement::visible()
                ->orderByDesc('is_pinned')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }
}
