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
        $countryRows = $this->paginated(
            fn (array $query) => $this->client->countries($query),
            ['data', 'countries', 'items', 'rows'],
        );

        $serviceRows = $this->paginated(
            fn (array $query) => $this->client->services($query),
            ['data', 'services', 'items', 'rows'],
        );

        $now = now();

        DB::transaction(function () use ($countryRows, $serviceRows, $now): void {
            foreach ($countryRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = (string) $this->first($row, [
                    'id',
                    'countryId',
                    '_id',
                    'uuid',
                    'code',
                ]);

                if ($id === '') {
                    continue;
                }

                SmsCountry::query()->updateOrCreate(
                    ['provider_id' => $id],
                    [
                        'name' => (string) (
                            $this->first($row, [
                                'name',
                                'countryName',
                                'label',
                                'title',
                            ]) ?: $id
                        ),
                        'iso_code' => $this->nullableString(
                            $this->first($row, [
                                'isoCode',
                                'iso',
                                'code',
                                'countryCode',
                                'shortName',
                            ]),
                        ),
                        'dial_code' => $this->nullableString(
                            $this->first($row, [
                                'dialCode',
                                'phoneCode',
                                'callingCode',
                                'prefix',
                            ]),
                        ),
                        'flag_url' => $this->nullableString(
                            $this->first($row, [
                                'flagUrl',
                                'imageUrl',
                                'flag',
                                'image',
                                'icon',
                            ]),
                        ),
                        'is_active' => $this->activeValue($row),
                        'provider_payload' => $row,
                        'synced_at' => $now,
                    ],
                );
            }

            foreach ($serviceRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = (string) $this->first($row, [
                    'id',
                    'serviceId',
                    '_id',
                    'uuid',
                    'code',
                ]);

                if ($id === '') {
                    continue;
                }

                $name = trim((string) (
                    $this->first($row, [
                        'name',
                        'serviceName',
                        'label',
                        'title',
                    ]) ?: $id
                ));

                SmsService::query()->updateOrCreate(
                    ['provider_id' => $id],
                    [
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'icon_url' => $this->nullableString(
                            $this->first($row, [
                                'iconUrl',
                                'imageUrl',
                                'icon',
                                'image',
                                'logo',
                            ]),
                        ),
                        'min_provider_price' => $this->numericOrNull(
                            $this->first($row, [
                                'minPrice',
                                'minimumPrice',
                                'priceMin',
                            ]),
                        ),
                        'max_provider_price' => $this->numericOrNull(
                            $this->first($row, [
                                'maxPrice',
                                'maximumPrice',
                                'priceMax',
                            ]),
                        ),
                        'is_active' => $this->activeValue($row),
                        'provider_payload' => $row,
                        'synced_at' => $now,
                    ],
                );
            }
        });

        $priceStats = $this->syncPriceCatalog(
            onlyCountriesWithoutPrices: false,
            syncedAt: $now,
        );

        return [
            'countryCount' => SmsCountry::query()
                ->where('is_active', true)
                ->count(),
            'serviceCount' => SmsService::query()
                ->where('is_active', true)
                ->count(),
            ...$priceStats,
        ];
    }

    /**
     * Continue only the price stage using countries and services already stored
     * in the database. When $onlyCountriesWithoutPrices is true, a rerun skips
     * countries that already have an active catalog row.
     */
    public function syncPricesOnly(
        bool $onlyCountriesWithoutPrices = true,
    ): array {
        return $this->syncPriceCatalog(
            onlyCountriesWithoutPrices: $onlyCountriesWithoutPrices,
            syncedAt: now(),
        );
    }

    public function reprice(): int
    {
        $count = 0;

        SmsServicePrice::query()->chunkById(
            500,
            function ($prices) use (&$count): void {
                foreach ($prices as $price) {
                    $price->update([
                        'sell_price' => $this->pricing->sellingPrice(
                            $price->provider_price,
                        ),
                    ]);

                    $count++;
                }
            },
        );

        return $count;
    }

    private function syncPriceCatalog(
        bool $onlyCountriesWithoutPrices,
        mixed $syncedAt,
    ): array {
        $countriesProcessed = 0;
        $serviceCountryRows = 0;
        $priceRowsWritten = 0;

        $countryQuery = SmsCountry::query()
            ->where('is_active', true)
            ->orderBy('id');

        if ($onlyCountriesWithoutPrices) {
            $countryQuery->whereDoesntHave(
                'prices',
                fn ($query) => $query->where('is_active', true),
            );
        }

        $countryQuery->chunkById(
            10,
            function ($countries) use (
                &$countriesProcessed,
                &$serviceCountryRows,
                &$priceRowsWritten,
                $syncedAt,
            ): void {
                foreach ($countries as $country) {
                    $rows = $this->paginated(
                        fn (array $query) => $this->client->servicesByCountry(
                            $country->provider_id,
                            $query,
                        ),
                        ['data', 'services', 'items', 'rows'],
                    );

                    DB::transaction(function () use (
                        $country,
                        $rows,
                        &$serviceCountryRows,
                        &$priceRowsWritten,
                        $syncedAt,
                    ): void {
                        foreach ($rows as $serviceRow) {
                            if (! is_array($serviceRow)) {
                                continue;
                            }

                            $serviceProviderId = (string) $this->first(
                                $serviceRow,
                                [
                                    'id',
                                    'serviceId',
                                    'service.id',
                                    'service.serviceId',
                                    '_id',
                                    'uuid',
                                    'code',
                                ],
                            );

                            $serviceName = trim((string) (
                                $this->first($serviceRow, [
                                    'name',
                                    'serviceName',
                                    'service.name',
                                    'service.serviceName',
                                    'label',
                                    'title',
                                ]) ?: $serviceProviderId
                            ));

                            if (
                                $serviceProviderId === ''
                                && $serviceName === ''
                            ) {
                                continue;
                            }

                            $service = SmsService::query()->updateOrCreate(
                                [
                                    'provider_id' => $serviceProviderId !== ''
                                        ? $serviceProviderId
                                        : hash('sha256', $serviceName),
                                ],
                                [
                                    'name' => $serviceName ?: 'Layanan',
                                    'slug' => Str::slug(
                                        $serviceName ?: 'layanan',
                                    ),
                                    'icon_url' => $this->nullableString(
                                        $this->first($serviceRow, [
                                            'iconUrl',
                                            'imageUrl',
                                            'icon',
                                            'image',
                                            'logo',
                                        ]),
                                    ),
                                    'is_active' => $this->activeValue(
                                        $serviceRow,
                                    ),
                                    'provider_payload' => $serviceRow,
                                    'synced_at' => $syncedAt,
                                ],
                            );

                            $candidate = $this->selectPriceCandidate(
                                $serviceRow,
                            );

                            if ($candidate === null) {
                                continue;
                            }

                            $priceRow = $candidate['row'];
                            $providerPriceId = $candidate['id'];
                            $providerPrice = $candidate['amount'];
                            $candidateCount = $candidate['candidate_count'];

                            $explicitStock = $this->first(
                                $priceRow,
                                [
                                    'stock',
                                    'stocks',
                                    'available',
                                    'quantity',
                                    'totalStock',
                                    'stockCount',
                                ],
                            );

                            if ($explicitStock === null) {
                                $explicitStock = $this->first(
                                    $serviceRow,
                                    [
                                        'stock',
                                        'stocks',
                                        'available',
                                        'quantity',
                                        'totalStock',
                                        'stockCount',
                                    ],
                                );
                            }

                            $active = $this->activeValue($serviceRow)
                                && $this->activeValue($priceRow);

                            $stock = $explicitStock !== null
                                ? max(0, (int) $explicitStock)
                                : ($active ? max(1, $candidateCount) : 0);

                            SmsServicePrice::query()
                                ->where('sms_country_id', $country->id)
                                ->where('sms_service_id', $service->id)
                                ->where(
                                    'provider_price_id',
                                    '!=',
                                    $providerPriceId,
                                )
                                ->update([
                                    'is_active' => false,
                                    'stock' => 0,
                                ]);

                            SmsServicePrice::query()->updateOrCreate(
                                ['provider_price_id' => $providerPriceId],
                                [
                                    'sms_country_id' => $country->id,
                                    'sms_service_id' => $service->id,
                                    'provider_operator_id' => $this
                                        ->nullableString(
                                            $this->first($priceRow, [
                                                'operatorId',
                                                'providerOperatorId',
                                                'operator.id',
                                            ]),
                                        ),
                                    'operator_name' => $this->nullableString(
                                        $this->first($priceRow, [
                                            'operatorName',
                                            'operator.name',
                                            'operator',
                                            'network',
                                        ]),
                                    ),
                                    'provider_price' => $providerPrice,
                                    'sell_price' => $this->pricing
                                        ->sellingPrice($providerPrice),
                                    'stock' => $stock,
                                    'success_rate' => $this->numericOrNull(
                                        $this->first($priceRow, [
                                            'successRate',
                                            'success_rate',
                                            'rate',
                                            'successPercentage',
                                        ]) ?? $this->first(
                                            $serviceRow,
                                            [
                                                'successRate',
                                                'success_rate',
                                                'rate',
                                                'successPercentage',
                                            ],
                                        ),
                                    ),
                                    'is_active' => $active,
                                    'provider_payload' => [
                                        'service' => $serviceRow,
                                        'selected_price' => $priceRow,
                                        'candidate_count' => $candidateCount,
                                    ],
                                    'synced_at' => $syncedAt,
                                ],
                            );

                            $amounts = array_column(
                                $candidate['all_candidates'],
                                'amount',
                            );

                            $service->update([
                                'min_provider_price' => min($amounts),
                                'max_provider_price' => max($amounts),
                                'is_active' => $active,
                                'synced_at' => $syncedAt,
                            ]);

                            $serviceCountryRows++;
                            $priceRowsWritten++;
                        }

                        SmsServicePrice::query()
                            ->where('sms_country_id', $country->id)
                            ->where(function ($query) use ($syncedAt): void {
                                $query->whereNull('synced_at')
                                    ->orWhere('synced_at', '<', $syncedAt);
                            })
                            ->update([
                                'is_active' => false,
                                'stock' => 0,
                            ]);
                    });

                    $countriesProcessed++;
                }
            },
        );

        return [
            'countriesProcessed' => $countriesProcessed,
            'serviceCountryRows' => $serviceCountryRows,
            'priceRowsWritten' => $priceRowsWritten,
            'priceCount' => SmsServicePrice::query()
                ->where('is_active', true)
                ->count(),
            'availablePriceCount' => SmsServicePrice::query()
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->count(),
        ];
    }

    /**
     * The provider returns a service-country row with a nested prices[] array.
     * Each nested entry has its own orderable ID and sellPrice/promoPrice.
     * Keep one active catalog record per service-country and select the lowest
     * currently orderable price ID.
     */
    private function selectPriceCandidate(
        array $serviceRow,
    ): ?array {
        $valid = [];

        foreach ($this->priceRows($serviceRow) as $priceRow) {
            if (! is_array($priceRow)) {
                continue;
            }

            $id = (string) $this->first($priceRow, [
                'serviceCountryPriceId',
                'serviceCountryPrice.id',
                'id',
                'priceId',
                '_id',
                'uuid',
            ]);

            $amount = $this->numericOrNull(
                $this->first($priceRow, [
                    // request-single-service charges the selected price row's
                    // sellPrice. Prefer it over promotional/display-only fields
                    // so the KodeOTP quote cannot be lower than provider cost.
                    'sellPrice',
                    'promoPrice',
                    'price',
                    'amount',
                    'cost',
                    'providerPrice',
                    'basePrice',
                    'serviceCountryPrice.price',
                ]),
            );

            if ($amount === null) {
                $amount = $this->numericOrNull(
                    $this->first($serviceRow, [
                        'retailSellPrice',
                        'sellPrice',
                        'price',
                        'amount',
                    ]),
                );
            }

            if ($id === '' || $amount === null || $amount <= 0) {
                continue;
            }

            $valid[] = [
                'id' => $id,
                'amount' => $amount,
                'row' => $priceRow,
            ];
        }

        if ($valid === []) {
            return null;
        }

        usort(
            $valid,
            fn (array $left, array $right): int => $left['amount']
                <=> $right['amount'],
        );

        return [
            ...$valid[0],
            'candidate_count' => count($valid),
            'all_candidates' => $valid,
        ];
    }

    private function paginated(
        callable $request,
        array $keys,
        int $pageSize = 100,
    ): array {
        $all = [];
        $previousSignature = null;

        for ($page = 1; $page <= 100; $page++) {
            $response = $request([
                'page' => $page,
                'pageSize' => $pageSize,
            ]);

            $rows = $this->client->rows($response, $keys);

            if ($rows === []) {
                break;
            }

            $signature = hash(
                'sha256',
                json_encode(
                    $rows,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            );

            if ($signature === $previousSignature) {
                break;
            }

            $previousSignature = $signature;
            array_push($all, ...$rows);

            if (count($rows) < $pageSize) {
                break;
            }
        }

        return $all;
    }

    private function priceRows(array $serviceRow): array
    {
        foreach ([
            'prices',
            'priceList',
            'serviceCountryPrices',
            'serviceCountryPriceList',
            'variants',
            'offers',
        ] as $key) {
            $candidate = Arr::get($serviceRow, $key);

            if (is_array($candidate) && array_is_list($candidate)) {
                return $candidate;
            }
        }

        return [$serviceRow];
    }

    private function first(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function activeValue(array $row): bool
    {
        $value = $this->first($row, [
            'isActiveServiceCountry',
            'isActive',
            'active',
            'is_available',
            'isAvailable',
            'enabled',
        ]);

        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        return ! in_array(
            strtolower((string) $value),
            ['false', 'inactive', 'disabled', 'no', 'off', '0'],
            true,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === ''
            ? null
            : (string) $value;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value)
            ? (float) $value
            : null;
    }
}
