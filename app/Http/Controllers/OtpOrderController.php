<?php
namespace App\Http\Controllers;

use App\Jobs\PlaceOtpOrder;
use App\Models\OtpOrder;
use App\Models\SmsServicePrice;
use App\Services\OtpOrderStatusService;
use App\Services\PricingService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OtpOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('user.orders', ['orders' => OtpOrder::query()->where('user_id', $request->user()->id)->latest()->paginate(20)]);
    }

    public function store(Request $request, PricingService $pricing, WalletService $wallet): RedirectResponse
    {
        $data = $request->validate([
            'price_id' => ['required', 'integer', Rule::exists('sms_service_prices', 'id')->where('is_active', true)],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $existing = OtpOrder::query()->where('idempotency_key', $data['idempotency_key'])->where('user_id', $request->user()->id)->first();
        if ($existing) {
            if (! $existing->provider_activation_id && in_array($existing->status, ['processing', 'provider_pending'], true)) {
                PlaceOtpOrder::dispatch($existing->id);
            }
            return redirect()->route('orders.show', $existing);
        }

        $price = SmsServicePrice::query()->with(['service', 'country'])->findOrFail($data['price_id']);
        $sellPrice = $pricing->sellingPrice($price->provider_price);
        $price->update(['sell_price' => $sellPrice]);

        $order = DB::transaction(function () use ($request, $data, $price, $sellPrice, $wallet): OtpOrder {
            $order = OtpOrder::query()->create([
                'user_id' => $request->user()->id,
                'sms_service_price_id' => $price->id,
                'idempotency_key' => $data['idempotency_key'],
                'provider_price_id' => $price->provider_price_id,
                'provider_operator_id' => $price->provider_operator_id,
                'service_name' => $price->service->name,
                'country_name' => $price->country->name,
                'operator_name' => $price->operator_name,
                'provider_cost' => $price->provider_price,
                'sell_price' => $sellPrice,
                'status' => 'processing',
            ]);
            $wallet->debit($request->user(), $sellPrice, 'otp_order', 'order-debit:'.$order->id, 'Pembelian OTP '.$order->service_name.' — '.$order->country_name, OtpOrder::class, $order->id);
            return $order;
        }, 3);

        PlaceOtpOrder::dispatch($order->id);
        return redirect()->route('orders.show', $order)->with('success', 'Pemesanan diterima dan sedang diproses secara aman.');
    }

    public function show(Request $request, OtpOrder $order): View
    {
        $this->authorizeOwner($request, $order);
        return view('user.order-show', ['order' => $order]);
    }

    public function status(Request $request, OtpOrder $order, OtpOrderStatusService $service): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        if ($order->shouldPoll() && (! $order->last_synced_at || $order->last_synced_at->lt(now()->subSeconds(5)))) {
            try { $order = $service->sync($order); } catch (Throwable) { $order = $order->refresh(); }
        }
        return response()->json($this->publicPayload($order));
    }

    public function action(Request $request, OtpOrder $order, OtpOrderStatusService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        if (! $order->provider_activation_id) return back()->withErrors(['order' => 'Nomor masih diproses oleh provider.']);
        $data = $request->validate(['action' => ['required', Rule::in(['ready', 'resend', 'cancel', 'complete', 'reactivate'])]]);
        try {
            $service->action($order, $data['action']);
            return back()->with('success', 'Perintah '.$data['action'].' berhasil dikirim.');
        } catch (Throwable $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }
    }

    private function authorizeOwner(Request $request, OtpOrder $order): void
    {
        abort_unless($request->user()->id === $order->user_id || $request->user()->isAdmin(), 403);
    }
    private function publicPayload(OtpOrder $order): array
    {
        return [
            'id' => $order->id, 'status' => $order->status, 'phone_number' => $order->phone_number,
            'otp_code' => $order->otp_code, 'message' => $order->provider_message,
            'expires_at' => $order->expires_at?->toIso8601String(), 'otp_received_at' => $order->otp_received_at?->toIso8601String(),
            'terminal' => $order->isTerminal(),
        ];
    }
}
