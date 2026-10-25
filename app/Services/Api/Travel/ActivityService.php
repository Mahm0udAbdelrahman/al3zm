<?php

namespace App\Services\Api\Travel;

use App\Enums\ItemType;
use App\Services\Travel\Contracts\ActivityProvider;
use App\Services\Travel\Data\ActivitySearchQuery;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\Search\ResultSet;
use App\Services\Travel\Search\SearchAggregator;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\OfferStore;
use Throwable;

class ActivityService
{
    public function __construct(
        public SearchAggregator $aggregator,
        public ResultSet $results,
        public OfferStore $offers,
        public ProviderRegistry $providers,
        public CatalogService $catalog,
    ) {}

    public function search(array $data): array
    {
        $city = null;
        if (! empty($data['city'])) {
            try {
                $city = $this->catalog->city($data['city']);
            } catch (Throwable) {
                // Unknown id: search it as text.
            }
        }

        $query = new ActivitySearchQuery(
            cityId: $data['city'] ?? 'custom',
            cityName: $city['name'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : ($city['latitude'] ?? null),
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : ($city['longitude'] ?? null),
            query: $data['q'] ?? ($city ? null : ($data['city'] ?? null)),
            date: $data['date'] ?? null,
        );

        $search = $this->aggregator->search(
            ItemType::Activity,
            $query->toArray(),
            fn (ActivityProvider $provider) => $provider->searchActivities($query),
        );

        $page = $this->results->apply(ItemType::Activity, $search['offers'], $data + ['sort' => 'reviews']);

        return [
            'activities' => $page['items'],
            'meta' => [
                'search_id' => $search['search_id'],
                'currency' => config('travel.currency'),
                'providers' => $search['providers'],
                'pagination' => $page['pagination'],
            ],
        ];
    }

    public function details(string $activityId): array
    {
        return $this->provider($activityId)->activityDetails($this->offers->get($activityId));
    }

    /** Bookable start times; each slot id is what the order is made with. */
    public function availability(string $activityId, string $from, string $to): array
    {
        $result = $this->provider($activityId)->availability($this->offers->get($activityId), $from, $to);
        $this->offers->putMany($result['entries']);

        return $result['slots'];
    }

    private function provider(string $id): ActivityProvider
    {
        return $this->providers->get(ItemType::Activity, OfferRef::parse($id)->provider);
    }
}
