<?php

namespace App\Http\Controllers;

use App\Models\Topup;
use App\Services\PaymentGatewayManager;
use App\Services\TopupService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class TopupController extends Controller
{
    public function index(Request $request, Settings $settings, PaymentGatewayManager $gateways, TopupService $service): View
    {
        $service->expireStale();
        $gateway = $gateways->activeGateway();
        $minimum = (int) $settings->get('topup.minimum', 10000);
        if ($gateway === PaymentGatewayManager::DUITKU) {
            $minimum = max(10000, $minimum);
        }

        $methods = $gateways->paymentMethods($gateway);
        $defaultMethod = array_key_first($methods);

        return view('user.topups', [
            'topups' => Topup::query()->where('user_id', $request->user()->id)->latest()->paginate(15),
            'minimum' => $minimum,
            'maximum' => (int) $settings->get('topup.maximum', 5000000),
            'defaultMethod' => $defaultMethod,
            'paymentMethods' => $methods,
            'activeGateway' => $gateway,
            'activeGatewayLabel' => $gateways->label($gateway),
        ]);
    }

    public function store(
        Request $request,
        TopupService $service,
        Settings $settings,
        PaymentGatewayManager $gateways,
    ): RedirectResponse {
        if ($gateways->pendingGateway()) {
            return back()->withErrors([
                'topup' => 'Pergantian penyedia pembayaran sedang menunggu transaksi aktif selesai. Isi saldo baru dibuka kembali otomatis setelah pergantian selesai.',
            ])->withInput();
        }

        $gateway = $gateways->activeGateway();
        $min = (int) $settings->get('topup.minimum', 10000);
        if ($gateway === PaymentGatewayManager::DUITKU) {
            $min = max(10000, $min);
        }
        $max = (int) $settings->get('topup.maximum', 5000000);
        $methods = $gateways->paymentMethods($gateway);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'payment_method' => ['required', Rule::in(array_keys($methods))],
        ]);

        // Gateway dapat berubah tepat setelah form dibuka. Validasi ulang metode
        // terhadap gateway aktif saat submit supaya request lama tidak silang provider.
        $currentGateway = $gateways->activeGateway();
        if ($currentGateway !== $gateway) {
            return back()->withErrors([
                'topup' => 'Penyedia pembayaran baru saja berubah. Muat ulang halaman lalu buat invoice kembali.',
            ])->withInput();
        }

        try {
            $topup = $service->create($request->user(), (int) $data['amount'], $data['payment_method'], $gateway);
            $topup = $service->normalizeStatus($topup);

            if ($topup->status === 'expired') {
                return redirect()->route('topups.show', $topup)->withErrors([
                    'topup' => 'Penyedia tidak mengembalikan proses pembayaran yang dapat digunakan. Invoice langsung ditutup sebagai Kedaluwarsa.',
                ]);
            }

            return redirect()->route('topups.show', $topup)->with('success', 'Invoice isi saldo berhasil dibuat.');
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['topup' => 'Gagal membuat pembayaran: '.$e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, Topup $topup, PaymentGatewayManager $gateways, TopupService $service): View
    {
        $this->owner($request, $topup);
        $topup = $service->normalizeStatus($topup);
        $gateway = $topup->gateway ?: PaymentGatewayManager::PAKASIR;

        return view('user.topup-show', [
            'topup' => $topup,
            'paymentNumber' => $topup->payment_number,
            'providerError' => data_get($topup->provider_payload, 'error'),
            'gatewayLabel' => $gateways->label($gateway),
            'isQris' => $gateways->isQrisMethod($gateway, (string) $topup->payment_method),
        ]);
    }

    public function status(Request $request, Topup $topup, TopupService $service): JsonResponse
    {
        $this->owner($request, $topup);
        $topup = $service->normalizeStatus($topup);
        if ($topup->status === 'pending') {
            try {
                $topup = $service->verify($topup);
            } catch (Throwable) {
                $topup = $topup->refresh();
            }
        }

        return response()->json([
            'status' => $topup->status,
            'credited_at' => $topup->credited_at?->toIso8601String(),
            'expires_at' => $topup->expires_at?->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, Topup $topup, TopupService $service): RedirectResponse
    {
        $this->owner($request, $topup);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Topup::CANCELLATION_REASONS))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $note = $data['reason'] === 'other' ? ($data['note'] ?? null) : null;
            $service->cancel($topup, $data['reason'], $note);

            return redirect()->route('topups.show', $topup)->with('success', 'Invoice berhasil dibatalkan dan ditutup.');
        } catch (Throwable $e) {
            return back()->withErrors(['topup' => $e->getMessage()]);
        }
    }

    private function owner(Request $request, Topup $topup): void
    {
        abort_unless($request->user()->id === $topup->user_id || $request->user()->isAdmin(), 403);
    }
}
