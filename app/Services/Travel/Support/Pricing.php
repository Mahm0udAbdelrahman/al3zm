<?php

namespace App\Services\Travel\Support;

use App\Enums\ItemType;

/**
 * Turns a provider's price into the price the user sees and pays: converted
 * to the display currency, then marked up for its vertical.
 */
class Pricing
{
    public function __construct(public CurrencyConverter $converter) {}

    public function currency(): string
    {
        return $this->converter->base();
    }

    public function sell(float $amount, string $currency, ItemType $type): float
    {
        $markup = (float) config("travel.markup.{$type->value}", 0);
        $converted = $this->converter->convert($amount, $currency);

        return Currency::round($converted * (1 + $markup / 100), $this->currency());
    }
}
