<?php

namespace App\Services\Travel\Contracts;

use App\Services\Travel\Data\FlightSearchQuery;
use GuzzleHttp\Promise\PromiseInterface;

interface FlightProvider extends TravelProvider
{
    /**
     * Resolves to a list of `['offer' => itinerary, 'context' => […]]`.
     */
    public function searchFlights(FlightSearchQuery $query): PromiseInterface;
}
