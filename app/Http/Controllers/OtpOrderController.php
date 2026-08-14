<?php
namespace App\Http\Controllers;

use App\Jobs\PlaceOtpOrder;
use App\Models\OtpOrder;
use App\Models\SmsServicePrice;
use App\Services\OtpOrderStatusService;
use App\Services\PayKitaPaymentService;
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

    public function store(Request $request, PricingService $pricing, WalletService $wallet, PayKitaPaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'price_id' => ['required', 'integer', Rule::exists('sms_service_prices', 'id')->where('is_active', true)],
            'idempotency_key' => ['required', 'uuid'],
            'payment_channel' => ['required', Rule::in(['paykita', 'balance'])],
        ]);

        $existing = OtpOrder::query()->where('idempotency_key', $data['idempotency_key'])->where('user_id', $request->user()->id)->first();
        if ($existing) return redirect()->route('orders.show', $existing);

        $price = SmsServicePrice::query()->with(['service', 'country'])->findOrFail($data['price_id']);
        $sellPrice = $pricing->sellingPrice($price->provider_price);
        $price->update(['sell_price' => $sellPrice]);

        $order = DB::transaction(function () use ($request, $data, $price, $sellPrice, $pricing, $wallet): OtpOrder {
            $balancePayment = $data['payment_channel'] === 'balance' || $request->user()->isAdmin();
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
                'payment_channel' => $balancePayment ? 'balance' : 'paykita',
                'payment_status' => $balancePayment ? 'paid' : 'pending',
                'status' => $balancePayment ? 'processing' : 'awaiting_payment',
                'provider_message' => $balancePayment ? 'Pembayaran saldo diterima. Nomor sedang diproses.' : 'Menunggu pembayaran.',
            ]);

            if ($balancePayment && ! $request->user()->isAdmin()) {
                $wallet->debit($request->user(), $sellPrice, 'otp_order', 'order-debit:'.$order->id, 'Pembelian OTP '.$order->service_name.' — '.$order->country_name, OtpOrder::class, $order->id);
            }
            return $order;
        }, 3);

        if ($order->payment_channel === 'paykita') {
            try {
                $payments->createForOrder($order);
                return redirect()->route('orders.show', $order)->with('success', 'Pesanan dibuat. Selesaikan pembayaran untuk memproses nomor.');
            } catch (Throwable $e) {
                report($e);
                $order->update(['status' => 'payment_failed', 'provider_message' => 'Gagal membuat pembayaran. Silakan coba lagi.']);
                return redirect()->route('orders.show', $order)->withErrors(['order' => 'Gagal membuat pembayaran. Silakan coba lagi.']);
            }
        }

        $this->queuePlacement($order);
        return redirect()->route('orders.show', $order)->with('success', $request->user()->isAdmin() ? 'Pemesanan admin diterima dan akan menggunakan saldo provider.' : 'Pembayaran saldo berhasil. Pesanan sedang diproses.');
    }

    public function show(Request $request, OtpOrder $order): View
    {
        $this->authorizeOwner($request, $order);
        return view('user.order-show', ['order' => $order->refresh()]);
    }

    public function status(Request $request, OtpOrder $order, OtpOrderStatusService $service, PayKitaPaymentService $payments): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $order = $order->refresh();

        if ($order->payment_channel === 'paykita' && $order->payment_status === 'pending' && $order->paykita_order_id) {
            try { $order = $payments->syncOrder($order); } catch (Throwable) { $order = $order->refresh(); }
        }

        if ($order->payment_status === 'paid' && ! $order->provider_activation_id && in_array($order->status, ['processing', 'provider_pending'], true)) {
            $this->queuePlacement($order);
        }

        if ($order->shouldPoll() && (! $order->last_synced_at || $order->last_synced_at->lt(now()->subSeconds(3)))) {
            try { $order = $service->sync($order); } catch (Throwable $e) {
                $order = $order->refresh();
                if (! $order->provider_message) $order->provider_message = 'Pembaruan provider sementara gagal. Sistem akan mencoba lagi otomatis.';
            }
        }

        return response()->json($this->publicPayload($order));
    }

    public function action(Request $request, OtpOrder $order, OtpOrderStatusService $service, PayKitaPaymentService $payments): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $order);
        $data = $request->validate(['action' => ['required', Rule::in(['ready', 'resend', 'cancel', 'complete', 'reactivate'])]]);

        try {
            if ($data['action'] === 'cancel' && $order->payment_channel === 'paykita' && $order->payment_status === 'pending') {
                $updated = $payments->cancelOrder($order);
                $message = 'Pembayaran dibatalkan.';
            } else {
                $updated = $service->action($order->refresh(), $data['action']);
                $message = $data['action'] === 'cancel' && ! $updated->provider_activation_id
                    ? 'Pesanan dibatalkan sebelum nomor dialokasikan provider.'
                    : match ($data['action']) {
                        'ready' => 'Status SMS dikirim berhasil diteruskan.',
                        'resend' => 'Permintaan kirim ulang berhasil diteruskan.',
                        'complete' => 'Pesanan berhasil diselesaikan.',
                        'reactivate' => 'Permintaan aktifkan ulang berhasil diteruskan.',
                        'cancel' => 'Permintaan pembatalan berhasil diteruskan.',
                        default => 'Perintah berhasil dikirim.',
                    };
            }

            $updated = $updated->refresh();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => $message,
                    'data' => $this->publicPayload($updated),
                ]);
            }

            return back()->with('success', $message);
        } catch (Throwable $e) {
            $paymentCancel = $data['action'] === 'cancel'
                && $order->payment_channel === 'paykita'
                && $order->payment_status === 'pending';

            if ($paymentCancel) {
                report($e);
                $message = 'Pembayaran tidak dapat dibatalkan saat ini. Silakan coba lagi.';
            } else {
                $message = trim($e->getMessage()) !== '' ? $e->getMessage() : 'Aksi pesanan gagal diproses.';
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'data' => $this->publicPayload($order->refresh()),
                ], 422);
            }

            return back()->withErrors(['order' => $message]);
        }
    }

    private function queuePlacement(OtpOrder $order): void
    {
        try { PlaceOtpOrder::dispatch($order->id); }
        catch (Throwable $e) {
            report($e);
            OtpOrder::query()->whereKey($order->id)->whereNull('provider_activation_id')->whereIn('status', ['processing', 'provider_pending'])->update([
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
        $canCancelPayment = $order->payment_channel === 'paykita' && $order->payment_status === 'pending' && $order->status === 'awaiting_payment' && filled($order->paykita_order_id);

        return [
            'id' => $order->id,
            'status' => $order->status,
            'payment_channel' => $order->payment_channel,
            'payment_status' => $order->payment_status,
            'payment_pay_amount' => $order->payment_pay_amount,
            'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
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
                'cancel' => $canCancelPayment || $canCancelLocally || ($hasActivation && ! $terminal && ! $hasOtp),
                'reactivate' => $hasActivation && in_array($order->status, ['cancelled', 'expired', 'failed'], true),
            ],
        ];
    }
}
