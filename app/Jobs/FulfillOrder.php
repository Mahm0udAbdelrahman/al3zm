<?php

namespace App\Jobs;

use App\Services\Api\Travel\FulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Books a paid order with its providers. */
class FulfillOrder implements ShouldQueue
{
    use Queueable;

    // Bookings are not idempotent: never run twice on our own.
    public $tries = 1;

    public $timeout = 300;

    public function __construct(public int $orderId) {}

    public function handle(FulfillmentService $fulfillment): void
    {
        $fulfillment->fulfill($this->orderId);
    }
}
