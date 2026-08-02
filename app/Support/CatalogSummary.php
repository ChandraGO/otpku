<?php

namespace App\Support;

use App\Models\SmsService;
use App\Models\SmsServicePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

final class CatalogSummary
{
    /**
     * Build one aggregated price query instead of four correlated subqueries.
     */
    public static function query(
        ?int $countryId = null,
        bool $stockOnly = true,
    ): Builder {
        $priceStats = SmsServicePrice::query()
            ->select('sms_service_id')
            ->selectRaw('MIN(sell_price) AS lowest_price')
            ->selectRaw('MAX(sell_price) AS highest_price')
            ->selectRaw('COALESCE(SUM(stock), 0) AS total_stock')
            ->selectRaw('COUNT(*) AS available_variants')
            ->where('is_active', true)
            ->when(
                $countryId !== null,
                fn ($query) => $query->where('sms_country_id', $countryId),
            )
            ->when(
                $stockOnly,
                fn ($query) => $query->where('stock', '>', 0),
            )
            ->groupBy('sms_service_id');

        return SmsService::query()
            ->select('sms_services.*')
            ->joinSub(
                $priceStats,
                'catalog_price_stats',
                fn (JoinClause $join) => $join->on(
                    'catalog_price_stats.sms_service_id',
                    '=',
                    'sms_services.id',
                ),
            )
            ->addSelect([
                'catalog_price_stats.lowest_price',
                'catalog_price_stats.highest_price',
                'catalog_price_stats.total_stock',
                'catalog_price_stats.available_variants',
            ])
            ->where('sms_services.is_active', true);
    }
}
