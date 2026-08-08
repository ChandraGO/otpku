<?php
namespace App\Http\Controllers;

use App\Jobs\PlaceOtpOrder;
use App\Models\OtpOrder;
use App\Models\SmsServicePrice;
use App\Services\OtpOrderStatusService;
use App\Services\PaymentGatewayManager;
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

    public function store(Request $request, PricingService $pricing, WalletService $wallet, PaymentGatewayManager $gateways): RedirectResponse
    {
        if ($gateways->pendingGateway()) {
            return back()->withErrors([
                'order' => 'Sistem pembayaran sedang menyelesaikan transaksi aktif sebelum pergantian penyedia pembayaran. Pemesanan baru dibuka kembali otomatis setelah proses selesai.',
            ]);
        }

        $data = $request->validate([
            'price_id' => ['required', 'integer', Rule::exists('sms_service_prices', 'id')->where('is_active', true)],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $existing = OtpOrder::query()->where('idempotency_key', $data['idempotency_key'])->where('user_id', $request->user()->id)->first();
        if ($existing) {
            if (! $existing->provider_activation_id && in_array($existing->status, ['processing', 'provider_pending'], true)) {
                $this->queuePlacement($existing);
            }
            return redirect()->route('orders.show', $existing);
        }

        $price = SmsServicePrice::query()->with(['service', 'country'])->findOrFail($data['price_id']);
        $sellPrice = $pricing->sellingPrice($price->provider_price);
        $price->update(['sell_price' => $sellPrice]);

        $order = $gateways->withSwitchLock(function () use ($request, $data, $price, $sellPrice, $pricing, $wallet, $gateways): OtpOrder {
            if ($gateways->pendingGateway()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => 'Pergantian penyedia pembayaran sedang diproses. Pemesanan baru ditahan sementara sampai pergantian selesai.',
                ]);
            }

            return DB::transaction(function () use ($request, $data, $price, $sellPrice, $pricing, $wallet): OtpOrder {
                $order = OtpOrder::query()->create([
                    'user_id' => $request->user()->id,
                    'sms_service_price_id' => $price->id,
                    'idempotency_key' => $data['idempotency_key'],
                    'provider_price_id' => $price->provider_price_id,
                    'provider_operator_id' => $price->provider_operator_id,
                    'service_name' => $price->service->name,
                    'country_name' => $price->country->name,
                    'operator_name' => $price->operator_name,
                    'provider_cost' => $pricing->providerCostIdr($price->provider_price),
                    'sell_price' => $sellPrice,
                    'status' => 'processing',
                ]);
                // Administrator memakai saldo provider secara langsung. Hanya
                // akun pelanggan yang didebit dari wallet internal aplikasi.
                if (! $request->user()->isAdmin()) {
                    $wallet->debit($request->user(), $sellPrice, 'otp_order', 'order-debit:'.$order->id, 'Pembelian OTP '.$order->service_name.' — '.$order->country_name, OtpOrder::class, $order->id);
                }
                return $order;
            }, 3);
        });

        $this->queuePlacement($order);
        return redirect()->route('orders.show', $order)->with('success', $request->user()->isAdmin() ? 'Pemesanan admin diterima dan akan menggunakan saldo provider.' : 'Pemesanan diterima dan sedang diproses secara aman.');
    }

    public function show(Request $request, OtpOrder $order): View
    {
        $this->authorizeOwner($request, $order);
        return view('user.order-show', ['order' => $order]);
    }

    public function status(Request $request, OtpOrder $order, OtpOrderStatusService $service): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $order = $order->refresh();

        // Self-healing: bila worker sempat restart/down saat order dibuat,
        // polling dari halaman detail ikut memastikan job placement kembali
        // masuk queue. ShouldBeUnique pada job mencegah order ganda.
        if (! $order->provider_activation_id && in_array($order->status, ['processing', 'provider_pending'], true)) {
            $this->queuePlacement($order);
        }

        if ($order->shouldPoll() && (! $order->last_synced_at || $order->last_synced_at->lt(now()->subSeconds(3)))) {
            try {
                $order = $service->sync($order);
            } catch (Throwable $e) {
                $order = $order->refresh();
                if (! $order->provider_message) {
                    $order->provider_message = 'Pembaruan provider sementara gagal. Sistem akan mencoba lagi otomatis.';
                }
            }
        }

        return response()->json($this->publicPayload($order));
    }

    public function action(Request $request, OtpOrder $order, OtpOrderStatusService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        $data = $request->validate(['action' => ['required', Rule::in(['ready', 'resend', 'cancel', 'complete', 'reactivate'])]]);

        try {
            $updated = $service->action($order->refresh(), $data['action']);
            $message = $data['action'] === 'cancel' && ! $updated->provider_activation_id
                ? 'Pesanan dibatalkan sebelum nomor dialokasikan provider.'
                : 'Perintah '.$data['action'].' berhasil dikirim.';

            return back()->with('success', $message);
        } catch (Throwable $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }
    }


    private function queuePlacement(OtpOrder $order): void
    {
        try {
            PlaceOtpOrder::dispatch($order->id);
        } catch (Throwable $e) {
            report($e);
            OtpOrder::query()
                ->whereKey($order->id)
                ->whereNull('provider_activation_id')
                ->whereIn('status', ['processing', 'provider_pending'])
                ->update([
                    'status' => 'provider_pending',
                    'provider_message' => 'Antrian provider sementara tidak tersedia. Sistem akan mencoba lagi otomatis.',
                ]);
        }
    }

    private function authorizeOwner(Request $request, OtpOrder $order): void
    {
        abort_unless($request->user()->id === $order->user_id || $request->user()->isAdmin(), 403);
    }

    private function publicPayload(OtpOrder $order): array
    {
        $hasActivation = filled($order->provider_activation_id);
        $terminal = $order->isTerminal();
        $hasOtp = $order->hasOtp();
        $canCancelLocally = ! $hasActivation && in_array($order->status, ['processing', 'provider_pending'], true);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'phone_number' => $order->phone_number,
            'otp_code' => $order->otp_code,
            'message' => $order->provider_message,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'otp_received_at' => $order->otp_received_at?->toIso8601String(),
            'provider_activation_id' => $order->provider_activation_id,
            'terminal' => $terminal,
            'last_checked_at' => now()->toIso8601String(),
            'can' => [
                'ready' => $hasActivation && ! $terminal,
                'resend' => $hasActivation && ! $terminal,
                'complete' => $hasActivation && ! $terminal,
                'cancel' => $canCancelLocally || ($hasActivation && ! $terminal && ! $hasOtp),
                'reactivate' => $hasActivation && in_array($order->status, ['cancelled', 'expired', 'failed'], true),
            ],
        ];
    }
}
