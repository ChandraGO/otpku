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
    public function index(Request $request, Settings $settings): View
    {
        return view('user.topups', [
            'topups' => Topup::query()->where('user_id', $request->user()->id)->latest()->paginate(15),
            'minimum' => (int) $settings->get('topup.minimum', 10000),
            'maximum' => (int) $settings->get('topup.maximum', 5000000),
            'defaultMethod' => (string) $settings->get('topup.payment_method', 'qris'),
        ]);
    }
    public function store(Request $request, TopupService $service, Settings $settings): RedirectResponse
    {
        $min = (int) $settings->get('topup.minimum', 10000); $max = (int) $settings->get('topup.maximum', 5000000);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'payment_method' => ['required', Rule::in(['qris', 'cimb_niaga_va', 'bni_va', 'sampoerna_va', 'bnc_va', 'maybank_va', 'permata_va', 'atm_bersama_va', 'artha_graha_va', 'bri_va'])],
        ]);
        try {
            $topup = $service->create($request->user(), (int) $data['amount'], $data['payment_method']);
            return redirect()->route('topups.show', $topup)->with('success', 'Invoice top up berhasil dibuat.');
        } catch (Throwable $e) {
            return back()->withErrors(['topup' => 'Gagal membuat pembayaran: '.$e->getMessage()])->withInput();
        }
    }
    public function show(Request $request, Topup $topup): View
    {
        $this->owner($request, $topup);
        return view('user.topup-show', ['topup' => $topup]);
    }
    public function status(Request $request, Topup $topup, TopupService $service): JsonResponse
    {
        $this->owner($request, $topup);
        if ($topup->status === 'pending') { try { $topup = $service->verify($topup); } catch (Throwable) { $topup = $topup->refresh(); } }
        return response()->json(['status' => $topup->status, 'credited_at' => $topup->credited_at?->toIso8601String(), 'expires_at' => $topup->expires_at?->toIso8601String()]);
    }
    private function owner(Request $request, Topup $topup): void { abort_unless($request->user()->id === $topup->user_id || $request->user()->isAdmin(), 403); }
}
