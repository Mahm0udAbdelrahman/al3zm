<?php

namespace App\Services\Travel\Data;

/**
 * A fresh price for one item, straight from the provider, plus what the
 * provider needs to book it later (`data`, kept on the order item).
 */
final class Quote
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $title,
        public readonly array $summary = [],
        public readonly array $data = [],
        public readonly ?string $expiresAt = null,
        /** Paid extras inside `amount` (bags, seats), left out of the price-change check. */
        public readonly float $extras = 0,
    ) {}
}
