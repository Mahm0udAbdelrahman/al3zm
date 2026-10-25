<?php

namespace App\Services\Travel\Data;

final class CancellationResult
{
    public function __construct(
        public readonly bool $cancelled,
        /** What the provider gives back, in its currency; null = unknown/none. */
        public readonly ?float $refundAmount = null,
        public readonly ?string $currency = null,
        public readonly array $data = [],
        public readonly ?string $message = null,
    ) {}
}
