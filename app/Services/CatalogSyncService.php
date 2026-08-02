<?php

namespace App\Services;

use App\Models\SmsCountry;
use App\Models\SmsService;
use App\Models\SmsServicePrice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogSyncService
{
    public function __construct(
        private readonly SmsVirtualClient $client,
        private readonly PricingService $pricing,
    ) {}

    public function sync(): array
    {
        $countryRows = $this->paginated(fn (array $query) => $this->client->countries($query), ['data', 'countries']);
        $serviceRows = $this->paginated(fn (array $query) => $this->client->services($query), ['data', 'services']);
        $now = now();
        $countryCount = 0; $serviceCount = 0; $priceCount = 0;

        DB::transaction(function () use ($countryRows, $serviceRows, $now, &$countryCount, &$serviceCount): void {
            foreach ($countryRows as $row) {
                if (! is_array($row)) continue;
                $id = (string) $this->first($row, ['id', 'countryId', 'uuid', 'code']);
                if ($id === '') continue;
                SmsCountry::query()->updateOrCreate(['provider_id' => $id], [
                    'name' => (string) ($this->first($row, ['name', 'countryName', 'label']) ?: $id),
                    'iso_code' => $this->nullableString($this->first($row, ['isoCode', 'iso', 'code', 'countryCode'])),
                    'dial_code' => $this->nullableString($this->first($row, ['dialCode', 'phoneCode', 'callingCode'])),
                    'flag_url' => $this->nullableString($this->first($row, ['flagUrl', 'flag', 'image'])),
                    'is_active' => (bool) ($row['isActive'] ?? true),
                    'provider_payload' => $row,
                    'synced_at' => $now,
                ]);
                $countryCount++;
            }

            foreach ($serviceRows as $row) {
                if (! is_array($row)) continue;
                $id = (string) $this->first($row, ['id', 'serviceId', 'uuid', 'code']);
                if ($id === '') continue;
                SmsService::query()->updateOrCreate(['provider_id' => $id], [
                    'name' => (string) ($this->first($row, ['name', 'serviceName', 'label']) ?: $id),
                    'slug' => Str::slug((string) ($this->first($row, ['name', 'serviceName', 'label']) ?: $id)),
                    'icon_url' => $this->nullableString($this->first($row, ['iconUrl', 'icon', 'image'])),
                    'min_provider_price' => $this->numericOrNull($this->first($row, ['minPrice', 'minimumPrice'])),
                    'max_provider_price' => $this->numericOrNull($this->first($row, ['maxPrice', 'maximumPrice'])),
                    'is_active' => (bool) ($row['isActive'] ?? true),
                    'provider_payload' => $row,
                    'synced_at' => $now,
                ]);
                $serviceCount++;
            }
        });

        SmsCountry::query()->where('is_active', true)->orderBy('name')->chunk(20, function ($countries) use (&$priceCount, $now): void {
            foreach ($countries as $country) {
                $rows = $this->paginated(fn (array $query) => $this->client->servicesByCountry($country->provider_id, $query), ['data', 'services']);
                foreach ($rows as $serviceRow) {
                    if (! is_array($serviceRow)) continue;
                    $serviceProviderId = (string) $this->first($serviceRow, ['id', 'serviceId', 'uuid', 'code']);
                    $serviceName = (string) ($this->first($serviceRow, ['name', 'serviceName', 'label']) ?: $serviceProviderId);
                    $service = SmsService::query()->firstOrCreate(['provider_id' => $serviceProviderId ?: Str::uuid()->toString()], [
                        'name' => $serviceName ?: 'Layanan', 'slug' => Str::slug($serviceName ?: 'layanan'), 'is_active' => true, 'synced_at' => $now,
                    ]);
                    $priceRows = $serviceRow['prices'] ?? $serviceRow['priceList'] ?? null;
                    if (! is_array($priceRows) || ! array_is_list($priceRows)) $priceRows = [$serviceRow];

                    foreach ($priceRows as $priceRow) {
                        if (! is_array($priceRow)) continue;
                        $providerPriceId = (string) $this->first($priceRow, ['serviceCountryPriceId', 'id', 'priceId', 'uuid']);
                        $providerPrice = $this->numericOrNull($this->first($priceRow, ['price', 'amount', 'cost', 'providerPrice']));
                        if ($providerPriceId === '' || $providerPrice === null) continue;
                        SmsServicePrice::query()->updateOrCreate(['provider_price_id' => $providerPriceId], [
                            'sms_country_id' => $country->id,
                            'sms_service_id' => $service->id,
                            'provider_operator_id' => $this->nullableString($this->first($priceRow, ['operatorId', 'providerOperatorId'])),
                            'operator_name' => $this->nullableString($this->first($priceRow, ['operatorName', 'operator', 'network'])),
                            'provider_price' => $providerPrice,
                            'sell_price' => $this->pricing->sellingPrice($providerPrice),
                            'stock' => max(0, (int) ($this->first($priceRow, ['stock', 'stocks', 'available', 'quantity']) ?? 0)),
                            'success_rate' => $this->numericOrNull($this->first($priceRow, ['successRate', 'success_rate', 'rate'])),
                            'is_active' => (bool) ($priceRow['isActive'] ?? true),
                            'provider_payload' => ['service' => $serviceRow, 'price' => $priceRow],
                            'synced_at' => $now,
                        ]);
                        $priceCount++;
                    }
                }
            }
        });

        SmsServicePrice::query()->where('synced_at', '<', $now->copy()->subDay())->update(['is_active' => false]);
        return compact('countryCount', 'serviceCount', 'priceCount');
    }

    public function reprice(): int
    {
        $count = 0;
        SmsServicePrice::query()->chunkById(500, function ($prices) use (&$count): void {
            foreach ($prices as $price) {
                $price->update(['sell_price' => $this->pricing->sellingPrice($price->provider_price)]);
                $count++;
            }
        });
        return $count;
    }

    private function paginated(callable $request, array $keys, int $pageSize = 100): array
    {
        $all = [];
        $previousSignature = null;
        for ($page = 1; $page <= 100; $page++) {
            $response = $request(['page' => $page, 'pageSize' => $pageSize, 'limit' => $pageSize]);
            $rows = $this->client->rows($response, $keys);
            if ($rows === []) break;
            $signature = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($signature === $previousSignature) break;
            $previousSignature = $signature;
            array_push($all, ...$rows);
            if (count($rows) < $pageSize) break;
        }
        return $all;
    }

    private function first(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);
            if ($value !== null && $value !== '') return $value;
        }
        return null;
    }
    private function nullableString(mixed $value): ?string { return $value === null || $value === '' ? null : (string) $value; }
    private function numericOrNull(mixed $value): ?float { return is_numeric($value) ? (float) $value : null; }
}
