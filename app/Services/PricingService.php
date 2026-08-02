<?php

namespace App\Services;

use App\Support\Settings;

class PricingService
{
    public function __construct(private readonly Settings $settings) {}

    public function sellingPrice(float|int|string $providerPrice): float
    {
        $base = (float) $providerPrice;
        $percent = (float) $this->settings->get('pricing.markup_percent', 10);
        $fixed = (float) $this->settings->get('pricing.fixed_fee', 0);
        $roundTo = max(1, (int) $this->settings->get('pricing.round_to', 100));
        return ceil(($base * (1 + ($percent / 100)) + $fixed) / $roundTo) * $roundTo;
    }
}
