<?php

namespace App\Services;

use App\Support\Settings;

class PricingService
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * SMS Virtual exposes balance and service prices in the same monetary unit
     * that is charged from the provider account. Do not apply an extra
     * coin-to-IDR conversion here: it can make a Rp2.439 provider price appear
     * as roughly Rp1.000 in KodeOTP and cause the platform to sell below cost.
     */
    public function providerCostIdr(float|int|string $providerPrice): float
    {
        return round(max(0, (float) $providerPrice), 4);
    }

    public function sellingPrice(float|int|string $providerPrice): float
    {
        $baseIdr = $this->providerCostIdr($providerPrice);
        $percent = max(0, (float) $this->settings->get('pricing.markup_percent', 10));
        $fixed = max(0, (float) $this->settings->get('pricing.fixed_fee', 0));
        $roundTo = max(1, (int) $this->settings->get('pricing.round_to', 100));

        return ceil(($baseIdr * (1 + ($percent / 100)) + $fixed) / $roundTo) * $roundTo;
    }
}
