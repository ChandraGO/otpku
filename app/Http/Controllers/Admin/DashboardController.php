<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Services\SmsVirtualClient;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(
        SmsVirtualClient $client,
        Settings $settings,
    ): View {
        $providerBalanceRaw = null;
        $providerBalanceIdr = null;
        $providerError = null;

        $unitToIdr = max(
            0.0001,
            (float) $settings->get('sms_virtual.balance_unit_to_idr', 1),
        );
        $lowBalanceThreshold = max(
            0,
            (float) $settings->get('sms_virtual.low_balance_threshold', 5000),
        );
        $bufferPercent = max(
            0,
            (float) $settings->get('sms_virtual.reserve_buffer_percent', 20),
        );

        try {
            $response = Cache::remember(
                'sms-virtual:provider-balance:v2',
                now()->addSeconds(30),
                fn () => $client->balance(),
            );
            $providerBalanceRaw = $response['balance']
                ?? data_get($response, 'data.balance')
                ?? $response['data']
                ?? null;

            if (is_numeric($providerBalanceRaw)) {
                $providerBalanceIdr = (float) $providerBalanceRaw * $unitToIdr;
            }
        } catch (Throwable $e) {
            $providerError = $e->getMessage();
        }

        $userStats = User::query()
            ->where('role', 'user')
            ->selectRaw('COUNT(*) AS users')
            ->selectRaw('COALESCE(SUM(balance), 0) AS user_balance')
            ->first();
        $userBalance = (float) ($userStats->user_balance ?? 0);

        $orderStats = OtpOrder::query()
            ->where('created_at', '>=', today())
            ->selectRaw('COUNT(*) AS orders_today')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status NOT IN ('failed', 'refunded') THEN sell_price ELSE 0 END), 0) AS revenue_today",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status NOT IN ('failed', 'refunded') THEN sell_price - provider_cost ELSE 0 END), 0) AS profit_today",
            )
            ->first();

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
            $providerError !== null || $providerBalanceIdr === null => 'unknown',
            $providerBalanceIdr <= $lowBalanceThreshold => 'critical',
            $providerBalanceIdr < $reserveTarget => 'warning',
            default => 'healthy',
        };

        return view('admin.dashboard', [
            'stats' => [
                'users' => (int) ($userStats->users ?? 0),
                'user_balance' => $userBalance,
                'completed_topups' => (float) Topup::query()
                    ->where('status', 'completed')
                    ->sum('amount'),
                'orders_today' => (int) ($orderStats->orders_today ?? 0),
                'revenue_today' => (float) ($orderStats->revenue_today ?? 0),
                'profit_today' => (float) ($orderStats->profit_today ?? 0),
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
            'recentOrders' => OtpOrder::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(8)
                ->get(),
            'recentTopups' => Topup::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
