<?php

namespace App\Http\Controllers;

use App\Models\Topup;
use App\Services\DuitkuClient;
use App\Services\TopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DuitkuWebhookController extends Controller
{
    public function __invoke(Request $request, TopupService $service, DuitkuClient $client): Response
    {
        $validator = Validator::make($request->all(), [
            'merchantCode' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'merchantOrderId' => ['required', 'string', 'max:50'],
            'resultCode' => ['required', 'string', 'max:10'],
            'reference' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response('INVALID CALLBACK', 422);
        }

        $data = $validator->validated();
        if (! $client->verifyCallbackSignature($data)) {
            return response('INVALID SIGNATURE', 401);
        }

        $topup = Topup::query()
            ->where('order_id', $data['merchantOrderId'])
            ->where('gateway', 'duitku')
            ->first();

        if (! $topup || (int) $topup->amount !== (int) $data['amount']) {
            return response('INVOICE MISMATCH', 422);
        }

        if (filled($topup->provider_reference)
            && ! hash_equals((string) $topup->provider_reference, (string) $data['reference'])) {
            return response('REFERENCE MISMATCH', 422);
        }

        try {
            $service->verify($topup, force: true);
            return response('SUCCESS', 200);
        } catch (Throwable $e) {
            report($e);
            return response('VERIFICATION FAILED', 422);
        }
    }
}
