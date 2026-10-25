<?php

namespace App\Services\Travel\Contracts;

use App\Models\OrderItem;
use App\Services\Travel\Data\BookingResult;

/** A provider that can keep a seat or slot while the user pays. */
interface HoldsInventory
{
    /** Whether this item can be held (some fares must be paid at once). */
    public function canHold(OrderItem $item): bool;

    public function hold(OrderItem $item): BookingResult;

    /** Give a held item back, e.g. when the order expires unpaid. */
    public function release(OrderItem $item): void;
}
