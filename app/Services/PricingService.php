<?php

namespace App\Services;

use App\Support\Settings;

class PricingService
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Convert the raw provider unit/coin into the application's IDR ledger.
     *
     * SMS Virtuals exposes prices in its own balance unit. Keep the raw value
     * in sms_service_prices.provider_price, then convert it here before markup.
     */
    public function providerCostIdr(float|int|string $providerPrice): float
    {
        $rate = max(
            0.0001,
            (float) $this->settings->get('sms_virtual.balance_unit_to_idr', 1),
        );

        return round((float) $providerPrice * $rate, 4);
    }

    public function sellingPrice(float|int|string $providerPrice): float
    {
        $baseIdr = $this->providerCostIdr($providerPrice);
        $percent = (float) $this->settings->get('pricing.markup_percent', 10);
        $fixed = (float) $this->settings->get('pricing.fixed_fee', 0);
        $roundTo = max(1, (int) $this->settings->get('pricing.round_to', 100));

        return ceil(($baseIdr * (1 + ($percent / 100)) + $fixed) / $roundTo) * $roundTo;
    }
}
