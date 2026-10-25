<?php

namespace App\Services\Travel\Contracts;

use App\Enums\ItemType;
use App\Models\OrderItem;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\Data\CancellationResult;
use App\Services\Travel\Data\Quote;

/**
 * One provider for one vertical. Search lives on the vertical interfaces;
 * this is the booking lifecycle every provider shares.
 */
interface TravelProvider
{
    /** The prefix of every offer id this provider hands out, e.g. `duffel`. */
    public function key(): string;

    public function type(): ItemType;

    /** On and configured. */
    public function enabled(): bool;

    /**
     * Re-price an offer the user picked, before the order is created.
     *
     * @param  array{offer: array, context: array}  $entry  what the search stored
     * @param  array  $input  the order item as the app sent it (travellers, services…)
     */
    public function quote(array $entry, array $input): Quote;

    /** Book (or ticket a held booking) once the user has paid. */
    public function book(OrderItem $item): BookingResult;

    public function cancel(OrderItem $item): CancellationResult;
}
