<?php

namespace App\Services\Travel\Contracts;

use App\Services\Travel\Data\StaySearchQuery;
use GuzzleHttp\Promise\PromiseInterface;

interface StayProvider extends TravelProvider
{
    /**
     * Resolves to a list of `['offer' => hotel, 'context' => […]]`.
     */
    public function searchStays(StaySearchQuery $query): PromiseInterface;

    /**
     * The hotel with every bookable room/rate. Each room's id is an offer id
     * the app orders with; returns the entries to remember for them.
     *
     * @return array{hotel: array, entries: array<string, array{offer: array, context: array}>}
     */
    public function stayDetails(array $entry): array;
}
