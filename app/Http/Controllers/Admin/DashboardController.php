<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Services\ProviderBalanceService;
use App\Support\Settings;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Settings $settings, ProviderBalanceService $providerBalance): View
    {
        $balance = $providerBalance->get(refresh: true);
        $providerBalanceRaw = $balance['raw'];
        $providerBalanceIdr = $balance['idr'];
        $providerError = $balance['error'];
        $unitToIdr = (float) $balance['unit_to_idr'];

        $lowBalanceThreshold = $this->safe(fn () => max(
            0,
            (float) $settings->get('sms_virtual.low_balance_threshold', 5000),
        ), 5000.0);
        $bufferPercent = $this->safe(fn () => max(
            0,
            (float) $settings->get('sms_virtual.reserve_buffer_percent', 20),
        ), 20.0);

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
            'providerBalanceSource' => $balance['source'],
            'providerBalanceCheckedAt' => $balance['checked_at'],
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
