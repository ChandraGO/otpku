<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Topup;
use App\Services\TopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TopupController extends Controller
{
    public function index(Request $request): View
    {
        $topups = Topup::query()->with('user')->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest()->paginate(30)->withQueryString();
        return view('admin.topups.index', compact('topups'));
    }
    public function show(Topup $topup): View { return view('admin.topups.show', ['topup' => $topup->load('user')]); }
    public function verify(Topup $topup, TopupService $service): RedirectResponse
    {
        try { $service->verify($topup); return back()->with('success', 'Status pembayaran berhasil diverifikasi.'); }
        catch (Throwable $e) { return back()->withErrors(['topup' => $e->getMessage()]); }
    }
}
