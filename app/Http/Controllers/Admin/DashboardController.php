<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpOrder;
use App\Models\Topup;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Settings $settings): View
    {
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

        $providerBalanceRaw = Cache::get('sms-virtual:provider-balance:value');

        if (! is_numeric($providerBalanceRaw)) {
            $providerBalanceRaw = $settings->get(
                'sms_virtual.last_balance_raw',
                null,
            );
        }

        if (! is_numeric($providerBalanceRaw)) {
            $legacy = Cache::get('sms-virtual:provider-balance:v2');
            $providerBalanceRaw = is_array($legacy)
                ? ($legacy['balance']
                    ?? data_get($legacy, 'data.balance')
                    ?? (is_numeric($legacy['data'] ?? null)
                        ? $legacy['data']
                        : null))
                : (is_numeric($legacy) ? $legacy : null);
        }

        $providerBalanceIdr = is_numeric($providerBalanceRaw)
            ? (float) $providerBalanceRaw * $unitToIdr
            : null;
        $providerError = $providerBalanceIdr === null
            ? 'Saldo belum diperbarui. Buka Pengaturan → SMS Virtual lalu klik Tes saldo SMS Virtual.'
            : null;

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
            $providerBalanceIdr === null => 'unknown',
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
                    ->selectRaw(
                        'COALESCE(SUM(sell_price - provider_cost), 0) total',
                    )
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
