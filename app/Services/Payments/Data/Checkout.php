<?php

namespace App\Services\Payments\Data;

final class Checkout
{
    public function __construct(
        public readonly string $reference,
        public readonly string $redirectUrl,
        public readonly array $payload = [],
    ) {}
}
