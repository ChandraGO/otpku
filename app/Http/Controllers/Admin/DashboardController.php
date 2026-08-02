<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Services\SmsVirtualClient;
use App\Support\Settings;
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
            $response = $client->balance();
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

        $userBalance = (float) User::query()
            ->where('role', 'user')
            ->sum('balance');
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
                'users' => User::query()->where('role', 'user')->count(),
                'user_balance' => $userBalance,
                'completed_topups' => (float) Topup::query()
                    ->where('status', 'completed')
                    ->sum('amount'),
                'orders_today' => OtpOrder::query()
                    ->whereDate('created_at', today())
                    ->count(),
                'revenue_today' => (float) OtpOrder::query()
                    ->whereDate('created_at', today())
                    ->whereNotIn('status', ['failed', 'refunded'])
                    ->sum('sell_price'),
                'profit_today' => (float) OtpOrder::query()
                    ->whereDate('created_at', today())
                    ->whereNotIn('status', ['failed', 'refunded'])
                    ->selectRaw('COALESCE(SUM(sell_price - provider_cost), 0) total')
                    ->value('total'),
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
                ->with('user')
                ->latest()
                ->limit(8)
                ->get(),
            'recentTopups' => Topup::query()
                ->with('user')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
