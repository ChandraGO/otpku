<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        $unitToIdr = $this->safe(fn () => max(
            0.0001,
            (float) $settings->get('sms_virtual.balance_unit_to_idr', 1),
        ), 1.0);
        $lowBalanceThreshold = $this->safe(fn () => max(
            0,
            (float) $settings->get('sms_virtual.low_balance_threshold', 5000),
        ), 5000.0);
        $bufferPercent = $this->safe(fn () => max(
            0,
            (float) $settings->get('sms_virtual.reserve_buffer_percent', 20),
        ), 20.0);

        // Database-only; tidak memanggil API atau Redis dari dashboard.
        $providerBalanceRaw = $this->safe(
            fn () => $settings->get('sms_virtual.last_balance_raw'),
            null,
        );
        $providerBalanceRaw = is_numeric($providerBalanceRaw)
            ? (float) $providerBalanceRaw
            : null;
        $providerBalanceIdr = $providerBalanceRaw === null
            ? null
            : $providerBalanceRaw * $unitToIdr;
        $providerError = $providerBalanceIdr === null
            ? 'Saldo belum diperbarui. Buka Pengaturan → SMS Virtual lalu klik Tes saldo SMS Virtual.'
            : null;

        $users = (int) $this->safe(
            fn () => User::query()->where('role', 'user')->count(),
            0,
        );
        $userBalance = (float) $this->safe(
            fn () => User::query()->where('role', 'user')->sum('balance'),
            0,
        );
        $completedTopups = (float) $this->safe(
            fn () => Topup::query()->where('status', 'completed')->sum('amount'),
            0,
        );
        $ordersToday = (int) $this->safe(
            fn () => OtpOrder::query()->whereDate('created_at', today())->count(),
            0,
        );
        $revenueToday = (float) $this->safe(
            fn () => OtpOrder::query()
                ->whereDate('created_at', today())
                ->whereNotIn('status', ['failed', 'refunded'])
                ->sum('sell_price'),
            0,
        );
        $profitToday = (float) $this->safe(
            fn () => OtpOrder::query()
                ->whereDate('created_at', today())
                ->whereNotIn('status', ['failed', 'refunded'])
                ->selectRaw('COALESCE(SUM(sell_price - provider_cost), 0) AS total')
                ->value('total'),
            0,
        );

        $reserveTarget = $userBalance * (1 + ($bufferPercent / 100));
        $reserveGap = $providerBalanceIdr === null
            ? null
            : max(0, $reserveTarget - $providerBalanceIdr);
        $coveragePercent = $providerBalanceIdr === null
            ? null
            : ($userBalance > 0
                ? ($providerBalanceIdr / $userBalance) * 100
                : 100);

        $riskStatus = match (true) {
            $providerBalanceIdr === null => 'unknown',
            $providerBalanceIdr <= $lowBalanceThreshold => 'critical',
            $providerBalanceIdr < $reserveTarget => 'warning',
            default => 'healthy',
        };

        return view('admin.dashboard', [
            'stats' => [
                'users' => $users,
                'user_balance' => $userBalance,
                'completed_topups' => $completedTopups,
                'orders_today' => $ordersToday,
                'revenue_today' => $revenueToday,
                'profit_today' => $profitToday,
            ],
            'providerBalanceRaw' => $providerBalanceRaw,
            'providerBalanceIdr' => $providerBalanceIdr,
            'providerError' => $providerError,
            'providerUnitToIdr' => $unitToIdr,
            'lowBalanceThreshold' => $lowBalanceThreshold,
            'bufferPercent' => $bufferPercent,
            'reserveTarget' => $reserveTarget,
            'reserveGap' => $reserveGap,
            'coveragePercent' => $coveragePercent,
            'riskStatus' => $riskStatus,
            'recentOrders' => $this->safe(
                fn () => OtpOrder::query()->with('user')->latest()->limit(8)->get(),
                collect(),
            ),
            'recentTopups' => $this->safe(
                fn () => Topup::query()->with('user')->latest()->limit(8)->get(),
                collect(),
            ),
        ]);
    }

    private function safe(callable $callback, mixed $default): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            report($e);

            return $default;
        }
    }
}
