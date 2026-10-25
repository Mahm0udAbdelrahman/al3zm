<?php

namespace App\Services\Api\Travel;

use App\Enums\ItemType;
use App\Services\Travel\Contracts\StayProvider;
use App\Services\Travel\Data\StaySearchQuery;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\Search\ResultSet;
use App\Services\Travel\Search\SearchAggregator;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\OfferStore;

class StayService
{
    public function __construct(
        public SearchAggregator $aggregator,
        public ResultSet $results,
        public OfferStore $offers,
        public ProviderRegistry $providers,
        public CatalogService $catalog,
    ) {}

    public function defaults(?string $cityId = null): array
    {
        $city = $cityId ? $this->catalog->city($cityId) : $this->catalog->cities()[0];

        return [
            'city' => $city,
            'check_in' => now()->addDays(14)->toDateString(),
            'check_out' => now()->addDays(17)->toDateString(),
            'adults' => 2,
            'rooms' => 1,
        ];
    }

    public function search(array $data): array
    {
        $city = isset($data['latitude'], $data['longitude'])
            ? ['id' => $data['city'] ?? 'custom', 'latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude']]
            : $this->catalog->city($data['city']);

        $query = new StaySearchQuery(
            cityId: $city['id'],
            latitude: $city['latitude'],
            longitude: $city['longitude'],
            checkIn: substr($data['check_in'], 0, 10),
            checkOut: substr($data['check_out'], 0, 10),
            adults: (int) ($data['adults'] ?? 2),
            rooms: (int) ($data['rooms'] ?? 1),
            childrenAges: array_map('intval', $data['children_ages'] ?? []),
            radiusKm: isset($data['radius']) ? (int) $data['radius'] : null,
        );

        $search = $this->aggregator->search(
            ItemType::Stay,
            $query->toArray(),
            fn (StayProvider $provider) => $provider->searchStays($query),
        );

        return $this->page($search['search_id'], $search, $data);
    }

    public function results(string $searchId, array $options): array
    {
        return $this->page($searchId, $this->aggregator->find($searchId, ItemType::Stay), $options);
    }

    private function page(string $searchId, array $search, array $options): array
    {
        $page = $this->results->apply(ItemType::Stay, $search['offers'], $options);

        return [
            'hotels' => $page['items'],
            'meta' => [
                'search_id' => $searchId,
                'currency' => config('travel.currency'),
                'nights' => $search['query']['nights'],
                'providers' => $search['providers'],
                'pagination' => $page['pagination'],
            ],
        ];
    }

    /** The hotel with every room and rate; each room id is orderable. */
    public function details(string $hotelId): array
    {
        $entry = $this->offers->get($hotelId);
        $provider = $this->providers->get(ItemType::Stay, OfferRef::parse($hotelId)->provider);

        $details = $provider->stayDetails($entry);
        $this->offers->putMany($details['entries']);

        return $details['hotel'];
    }
}
