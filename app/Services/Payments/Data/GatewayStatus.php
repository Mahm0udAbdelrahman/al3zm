<?php

namespace App\Services\Payments\Data;

use App\Enums\PaymentStatus;

final class GatewayStatus
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $paymentId = null,
        public readonly array $payload = [],
    ) {}
}
