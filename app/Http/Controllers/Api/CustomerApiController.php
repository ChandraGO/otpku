<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PlaceOtpOrder;
use App\Models\OtpOrder;
use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Models\SmsServicePrice;
use App\Services\OtpOrderStatusService;
use App\Services\PricingService;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'telegram_id' => $user->telegram_id,
            'balance' => (float) $user->balance,
            'currency' => 'IDR',
            'default_country' => $user->defaultCountry?->only(['id', 'name', 'iso_code']),
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'balance' => (float) $user->balance,
            'currency' => 'IDR',
        ]);
    }

    public function countries(Request $request): JsonResponse
    {
        $countries = SmsCountry::query()
            ->select(['id', 'provider_id', 'name', 'iso_code', 'dial_code', 'flag_url'])
            ->where('is_active', true)
            ->whereHas('prices', fn (Builder $query) => $query->where('is_active', true)->where('stock', '>', 0))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn (Builder $nested) => $nested->where('name', 'ilike', $term)->orWhere('iso_code', 'ilike', $term));
            })
            ->orderBy('name')
            ->limit(min(max($request->integer('limit', 100), 1), 250))
            ->get();

        return $this->success($countries);
    }

    public function services(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id') ?: null;

        $services = SmsService::query()
            ->select(['sms_services.id', 'sms_services.provider_id', 'sms_services.name', 'sms_services.slug', 'sms_services.icon_url'])
            ->where('sms_services.is_active', true)
            ->when($request->filled('q'), fn (Builder $query) => $query->where('sms_services.name', 'ilike', '%'.$request->string('q')->trim().'%'))
            ->whereHas('prices', function (Builder $query) use ($countryId): void {
                $query->where('is_active', true)->where('stock', '>', 0);
                if ($countryId) $query->where('sms_country_id', $countryId);
            })
            ->withMin(['prices as lowest_price' => function (Builder $query) use ($countryId): void {
                $query->where('is_active', true)->where('stock', '>', 0);
                if ($countryId) $query->where('sms_country_id', $countryId);
            }], 'sell_price')
            ->withSum(['prices as total_stock' => function (Builder $query) use ($countryId): void {
                $query->where('is_active', true)->where('stock', '>', 0);
                if ($countryId) $query->where('sms_country_id', $countryId);
            }], 'stock')
            ->orderByDesc('total_stock')
            ->orderBy('sms_services.name')
            ->limit(min(max($request->integer('limit', 50), 1), 100))
            ->get();

        return $this->success($services);
    }

    public function prices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('sms_services', 'id')->where('is_active', true)],
            'country_id' => ['nullable', 'integer', Rule::exists('sms_countries', 'id')->where('is_active', true)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $prices = SmsServicePrice::query()
            ->with(['service:id,name,slug,icon_url', 'country:id,name,iso_code,dial_code,flag_url'])
            ->where('sms_service_id', $data['service_id'])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->when($data['country_id'] ?? null, fn (Builder $query, int $countryId) => $query->where('sms_country_id', $countryId))
            ->orderBy('sell_price')
            ->limit($data['limit'] ?? 50)
            ->get()
            ->map(fn (SmsServicePrice $price) => [
                'price_id' => $price->id,
                'service' => $price->service?->only(['id', 'name', 'slug', 'icon_url']),
                'country' => $price->country?->only(['id', 'name', 'iso_code', 'dial_code', 'flag_url']),
                'operator' => $price->operator_name,
                'price' => (float) $price->sell_price,
                'currency' => 'IDR',
                'stock' => (int) $price->stock,
                'success_rate' => $price->success_rate !== null ? (float) $price->success_rate : null,
            ]);

        return $this->success($prices);
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = OtpOrder::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(min(max($request->integer('limit', 20), 1), 100))
            ->get()
            ->map(fn (OtpOrder $order) => $this->orderPayload($order));

        return $this->success($orders);
    }

    public function createOrder(Request $request, PricingService $pricing, WalletService $wallet): JsonResponse
    {
        $data = $request->validate([
            'price_id' => ['required', 'integer', Rule::exists('sms_service_prices', 'id')->where('is_active', true)],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $existing = OtpOrder::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            if (! $existing->provider_activation_id && in_array($existing->status, ['processing', 'provider_pending'], true)) {
                $this->queuePlacement($existing);
            }

            return $this->success($this->orderPayload($existing), 'Pesanan idempoten ditemukan.', 200);
        }

        $price = SmsServicePrice::query()->with(['service', 'country'])->findOrFail($data['price_id']);
        if (! $price->is_active || $price->stock < 1) {
            return $this->error('Harga atau stok tidak tersedia.', 'PRICE_UNAVAILABLE', 422);
        }

        $sellPrice = $pricing->sellingPrice($price->provider_price);
        $price->update(['sell_price' => $sellPrice]);

        try {
            $order = DB::transaction(function () use ($request, $data, $price, $sellPrice, $pricing, $wallet): OtpOrder {
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
                    'status' => 'processing',
                ]);

                $wallet->debit(
                    $request->user(),
                    $sellPrice,
                    'otp_order',
                    'order-debit:'.$order->id,
                    'Pembelian OTP '.$order->service_name.' — '.$order->country_name,
                    OtpOrder::class,
                    $order->id,
                );

                return $order;
            }, 3);
        } catch (ValidationException $exception) {
            return $this->error(
                collect($exception->errors())->flatten()->first() ?: 'Data pesanan tidak valid.',
                'ORDER_VALIDATION_FAILED',
                422,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Pesanan belum dapat diproses. Silakan coba kembali.', 'ORDER_FAILED', 422);
        }

        $this->queuePlacement($order);

        return $this->success($this->orderPayload($order), 'Pesanan diterima dan sedang diproses.', 202);
    }

    public function showOrder(Request $request, OtpOrder $order, OtpOrderStatusService $service): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $order = $order->refresh();

        if (! $order->provider_activation_id && in_array($order->status, ['processing', 'provider_pending'], true)) {
            $this->queuePlacement($order);
        }

        if ($order->shouldPoll() && (! $order->last_synced_at || $order->last_synced_at->lt(now()->subSeconds(3)))) {
            try { $order = $service->sync($order); } catch (Throwable) { $order = $order->refresh(); }
        }

        return $this->success($this->orderPayload($order));
    }

    public function action(Request $request, OtpOrder $order, OtpOrderStatusService $service): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        $data = $request->validate([
            'action' => ['required', Rule::in(['ready', 'resend', 'cancel', 'complete', 'reactivate'])],
        ]);

        try {
            $updated = $service->action($order->refresh(), $data['action']);
            return $this->success($this->orderPayload($updated), 'Perintah berhasil dikirim.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->error('Aksi belum dapat diproses untuk status pesanan saat ini.', 'ACTION_FAILED', 422);
        }
    }

    private function queuePlacement(OtpOrder $order): void
    {
        try {
            PlaceOtpOrder::dispatch($order->id);
        } catch (Throwable $exception) {
            report($exception);
            OtpOrder::query()
                ->whereKey($order->id)
                ->whereNull('provider_activation_id')
                ->whereIn('status', ['processing', 'provider_pending'])
                ->update([
                    'status' => 'provider_pending',
                    'provider_message' => 'Antrian provider sementara tidak tersedia. Sistem akan mencoba lagi otomatis.',
                ]);
        }
    }

    private function authorizeOwner(Request $request, OtpOrder $order): void
    {
        abort_unless($request->user()->id === $order->user_id, 404);
    }

    private function orderPayload(OtpOrder $order): array
    {
        return [
            'id' => $order->id,
            'service' => $order->service_name,
            'country' => $order->country_name,
            'operator' => $order->operator_name,
            'status' => $order->status,
            'phone_number' => $order->phone_number,
            'otp_code' => $order->otp_code,
            'message' => $order->provider_message,
            'price' => (float) $order->sell_price,
            'currency' => 'IDR',
            'expires_at' => $order->expires_at?->toIso8601String(),
            'otp_received_at' => $order->otp_received_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'terminal' => $order->isTerminal(),
        ];
    }

    private function success(mixed $data, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => ['code' => $code],
        ], $status);
    }
}
