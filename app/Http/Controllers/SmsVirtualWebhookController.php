<?php
namespace App\Http\Controllers;

use App\Models\OtpOrder;
use App\Services\OtpOrderStatusService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SmsVirtualWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, Settings $settings, OtpOrderStatusService $service): JsonResponse
    {
        $configured = (string) $settings->get('security.provider_webhook_secret', '');
        abort_if($configured === '' || ! hash_equals($configured, $secret), 403);
        $payload = $request->all();
        $activationId = Arr::get($payload, 'activationId') ?? Arr::get($payload, 'data.activationId') ?? Arr::get($payload, 'id');
        if (! $activationId) return response()->json(['ok' => false, 'message' => 'activation id required'], 422);
        $order = OtpOrder::query()->where('provider_activation_id', (string) $activationId)->first();
        if (! $order) return response()->json(['ok' => true, 'ignored' => true]);
        $service->apply($order, $payload);
        return response()->json(['ok' => true]);
    }
}
