<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PlaceOtpOrder;
use App\Models\OtpOrder;
use App\Services\OtpOrderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = OtpOrder::query()->with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($x) => $x->where('service_name', 'ilike', $term)->orWhere('phone_number', 'ilike', $term)->orWhere('provider_activation_id', 'ilike', $term));
            })->latest()->paginate(30)->withQueryString();
        return view('admin.orders.index', compact('orders'));
    }
    public function show(OtpOrder $order): View { return view('admin.orders.show', ['order' => $order->load('user')]); }
    public function action(Request $request, OtpOrder $order, OtpOrderStatusService $service): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['retry', 'sync', 'ready', 'resend', 'cancel', 'complete', 'reactivate'])]]);
        try {
            if ($data['action'] === 'retry') {
                abort_if($order->provider_activation_id || ! in_array($order->status, ['processing', 'provider_pending'], true), 422, 'Pesanan ini tidak memerlukan retry provider.');
                PlaceOtpOrder::dispatch($order->id);
            } elseif ($data['action'] === 'sync') {
                $service->sync($order);
            } else {
                abort_unless($order->provider_activation_id, 422, 'Activation ID provider belum tersedia.');
                $service->action($order, $data['action']);
            }
            return back()->with('success', 'Pesanan berhasil diperbarui.');
        } catch (Throwable $e) { return back()->withErrors(['order' => $e->getMessage()]); }
    }
}
