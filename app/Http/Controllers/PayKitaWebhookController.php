<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use App\Models\Topup;
use App\Services\PayKitaPaymentService;
use App\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PayKitaWebhookController extends Controller
{
    public function __invoke(Request $request, PayKitaPaymentService $orders, TopupService $topups): JsonResponse
    {
        $remoteId = trim((string) data_get($request->all(), 'data.order_id', data_get($request->all(), 'data.id', '')));
        $reference = trim((string) data_get($request->all(), 'data.reference', ''));

        try {
            $order = $remoteId !== '' ? OtpOrder::query()->where('paykita_order_id', $remoteId)->first() : null;
            if (! $order && str_starts_with($reference, 'OTP-')) {
                $order = OtpOrder::query()->find(substr($reference, 4));
            }
            if ($order) {
                $orders->syncOrder($order);
                return response()->json(['ok' => true]);
            }

            $topup = $remoteId !== '' ? Topup::query()->where('provider_reference', $remoteId)->first() : null;
            if (! $topup && $reference !== '') $topup = Topup::query()->where('order_id', $reference)->first();
            if ($topup) {
                $topups->verify($topup, true);
                return response()->json(['ok' => true]);
            }
        } catch (Throwable $e) {
            report($e);
            return response()->json(['ok' => false], 503);
        }

        return response()->json(['ok' => true]);
    }
}
