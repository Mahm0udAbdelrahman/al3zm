<?php

namespace App\Console\Commands;

use App\Services\Api\Travel\OrderService;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'travel:expire-orders';

    protected $description = 'Release the holds of unpaid orders past their payment window';

    public function handle(OrderService $orders): int
    {
        $this->info($orders->expireStale().' order(s) expired.');

        return self::SUCCESS;
    }
}
