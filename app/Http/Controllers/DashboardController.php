<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\OtpOrder;
use App\Models\SmsService;
use App\Models\Topup;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->refresh();
        $available = fn (Builder $builder) => $builder
            ->where('is_active', true)
            ->where('stock', '>', 0);

        return view('user.dashboard', [
            'user' => $user,
            'announcements' => Announcement::visible()
                ->orderByDesc('is_pinned')
                ->latest()
                ->limit(5)
                ->get(),
            'activeOrders' => OtpOrder::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'expired', 'refunded', 'failed'])
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
            'featuredServices' => SmsService::query()
                ->where('is_active', true)
                ->whereHas('prices', $available)
                ->withMin(['prices as lowest_price' => $available], 'sell_price')
                ->withMax(['prices as highest_price' => $available], 'sell_price')
                ->withSum(['prices as total_stock' => $available], 'stock')
                ->orderByDesc('total_stock')
                ->limit(8)
                ->get(),
        ]);
    }
}
