<?php
namespace App\Http\Controllers;

use App\Models\Topup;
use App\Services\PakasirClient;
use App\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PakasirWebhookController extends Controller
{
    public function __invoke(Request $request, TopupService $service, PakasirClient $client): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'project' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:30'],
        ]);
        if (! hash_equals($client->project(), (string) $data['project'])) {
            return response()->json(['ok' => false, 'message' => 'project mismatch'], 422);
        }
        $topup = Topup::query()->where('order_id', $data['order_id'])->first();
        if (! $topup || (int) $topup->amount !== (int) $data['amount']) {
            return response()->json(['ok' => false, 'message' => 'invoice mismatch'], 422);
        }
        try {
            $verified = $service->verify($topup);
            return response()->json(['ok' => true, 'status' => $verified->status]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'verification failed'], 422);
        }
    }
}
