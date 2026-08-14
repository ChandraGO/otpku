<?php

namespace App\Http\Controllers;

use App\Models\Topup;
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
    public function index(Request $request, Settings $settings, TopupService $service): View
    {
        $service->expireStale();
        return view('user.topups', [
            'topups' => Topup::query()->where('user_id', $request->user()->id)->latest()->paginate(15),
            'minimum' => (int) $settings->get('topup.minimum', 10000),
            'maximum' => (int) $settings->get('topup.maximum', 5000000),
            'defaultMethod' => 'qris',
            'paymentMethods' => ['qris' => 'QRIS'],
            'activeGateway' => 'paykita',
            'activeGatewayLabel' => 'QRIS',
        ]);
    }

    public function store(Request $request, TopupService $service, Settings $settings): RedirectResponse
    {
        $min = (int) $settings->get('topup.minimum', 10000);
        $max = (int) $settings->get('topup.maximum', 5000000);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'payment_method' => ['required', Rule::in(['qris'])],
        ]);
        try {
            $topup = $service->create($request->user(), (int) $data['amount']);
            return redirect()->route('topups.show', $topup)->with('success', 'Invoice pembayaran berhasil dibuat.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['topup' => 'Gagal membuat pembayaran. Silakan coba lagi.'])->withInput();
        }
    }

    public function show(Request $request, Topup $topup, TopupService $service): View
    {
        $this->owner($request, $topup);
        $topup = $service->normalizeStatus($topup);
        return view('user.topup-show', [
            'topup' => $topup,
            'paymentNumber' => $topup->payment_number,
            'providerError' => $topup->status === 'failed' ? 'Pembayaran belum dapat dibuat. Silakan buat invoice baru atau hubungi admin.' : null,
            'gatewayLabel' => 'QRIS',
            'isQris' => true,
        ]);
    }

    public function status(Request $request, Topup $topup, TopupService $service): JsonResponse
    {
        $this->owner($request, $topup);
        try { $topup = $service->verify($topup); } catch (Throwable) { $topup = $topup->refresh(); }
        return response()->json(['status' => $topup->status, 'credited_at' => $topup->credited_at?->toIso8601String(), 'expires_at' => $topup->expires_at?->toIso8601String()]);
    }

    public function cancel(Request $request, Topup $topup, TopupService $service): RedirectResponse
    {
        $this->owner($request, $topup);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(Topup::CANCELLATION_REASONS))], 'note' => ['nullable', 'string', 'max:500']]);
        try {
            $service->cancel($topup, $data['reason'], $data['reason'] === 'other' ? ($data['note'] ?? null) : null);
            return redirect()->route('topups.show', $topup)->with('success', 'Invoice pembayaran dibatalkan.');
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['topup' => 'Invoice tidak dapat dibatalkan saat ini. Silakan coba lagi.']);
        }
    }

    private function owner(Request $request, Topup $topup): void
    {
        abort_unless($request->user()->id === $topup->user_id || $request->user()->isAdmin(), 403);
    }
}
