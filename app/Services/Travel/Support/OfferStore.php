<?php

namespace App\Services\Travel\Support;

use App\Services\Travel\Exceptions\TravelException;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers what a search returned — the normalized offer plus whatever the
 * provider needs later to price and book it — for `travel.search_ttl`.
 */
class OfferStore
{
    /** @param array{offer: array, context?: array} $entry */
    public function put(string $offerId, array $entry): void
    {
        Cache::put($this->key($offerId), $entry, config('travel.search_ttl'));
    }

    /** @param array<string, array{offer: array, context?: array}> $entries */
    public function putMany(array $entries): void
    {
        $keyed = [];
        foreach ($entries as $offerId => $entry) {
            $keyed[$this->key($offerId)] = $entry;
        }
        if ($keyed) {
            Cache::putMany($keyed, config('travel.search_ttl'));
        }
    }

    /** @return array{offer: array, context: array} */
    public function get(string $offerId): array
    {
        $entry = Cache::get($this->key($offerId));
        if (! $entry) {
            throw new TravelException(__('This offer has expired, please search again.'), 410);
        }

        return $entry + ['context' => []];
    }

    public function find(string $offerId): ?array
    {
        $entry = Cache::get($this->key($offerId));

        return $entry ? $entry + ['context' => []] : null;
    }

    private function key(string $offerId): string
    {
        return 'travel:offer:'.$offerId;
    }
}
