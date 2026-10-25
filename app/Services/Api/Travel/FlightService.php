<?php

namespace App\Services\Api\Travel;

use App\Enums\ItemType;
use App\Services\Travel\Contracts\FlightProvider;
use App\Services\Travel\Data\FlightSearchQuery;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\Providers\Duffel\DuffelFlightProvider;
use App\Services\Travel\Search\ResultSet;
use App\Services\Travel\Search\SearchAggregator;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\OfferStore;

class FlightService
{
    public function __construct(
        public SearchAggregator $aggregator,
        public ResultSet $results,
        public OfferStore $offers,
        public ProviderRegistry $providers,
        public CatalogService $catalog,
    ) {}

    public function defaults(?string $to = null): array
    {
        $airports = collect($this->catalog->airports())->keyBy('code');
        $from = $airports->get('MCT') ?? $airports->first();
        $destination = ($to ? $airports->get(strtoupper($to)) : null) ?? $airports->get('DXB') ?? $airports->last();

        return [
            'trip_type' => 'roundTrip',
            'legs' => [['from' => $from, 'to' => $destination, 'date' => now()->addDays(14)->toDateString()]],
            'return_date' => now()->addDays(21)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'cabin' => 'economy',
        ];
    }

    public function search(array $data): array
    {
        $query = FlightSearchQuery::fromArray($data);
        $search = $this->aggregator->search(
            ItemType::Flight,
            $query->toArray(),
            fn (FlightProvider $provider) => $provider->searchFlights($query),
        );

        return $this->page($search['search_id'], $search, $data);
    }

    public function results(string $searchId, array $options): array
    {
        return $this->page($searchId, $this->aggregator->find($searchId, ItemType::Flight), $options);
    }

    private function page(string $searchId, array $search, array $options): array
    {
        $page = $this->results->apply(ItemType::Flight, $search['offers'], $options);

        return [
            'search_id' => $searchId,
            'currency' => config('travel.currency'),
            'itineraries' => $page['items'],
            'pagination' => $page['pagination'],
            'providers' => $search['providers'],
            'airlines' => collect($search['offers'])->flatMap(fn ($o) => array_column($o['segments'], 'airline'))
                ->unique('code')->values(),
        ];
    }

    /** Fresh price plus the extras (bags) the traveller can add. */
    public function offer(string $offerId): array
    {
        $entry = $this->offers->get($offerId);
        $provider = $this->providers->get(ItemType::Flight, OfferRef::parse($offerId)->provider);

        if ($provider instanceof DuffelFlightProvider) {
            return $provider->services($entry);
        }

        return ['offer' => $entry['offer'], 'services' => [], 'passengers' => []];
    }

    public function seatMap(string $offerId): array
    {
        $entry = $this->offers->get($offerId);
        $provider = $this->providers->get(ItemType::Flight, OfferRef::parse($offerId)->provider);

        if (! $provider instanceof DuffelFlightProvider) {
            throw new TravelException(__('Seat selection is not available for this flight.'), 404);
        }

        return $provider->seatMap($entry);
    }
}
