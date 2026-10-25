<?php

namespace App\Services\Travel\Search;

use App\Enums\ItemType;
use App\Services\Travel\Contracts\TravelProvider;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\ProviderRegistry;
use App\Services\Travel\Support\OfferStore;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Asks every enabled provider of a vertical at once, merges what they
 * return, drops duplicates (the same flight or hotel from two providers keeps
 * the cheaper one) and remembers the result so it can be re-sorted and paged
 * without searching again.
 *
 * One provider failing never fails the search: its error is reported in
 * `providers` and the others' offers are returned.
 */
class SearchAggregator
{
    public function __construct(private ProviderRegistry $registry, private OfferStore $offers) {}

    /**
     * @param  callable(TravelProvider): PromiseInterface  $search
     * @return array{search_id: string, query: array, offers: list<array>, providers: array<string, array>}
     */
    public function search(ItemType $type, array $query, callable $search): array
    {
        $providers = $this->registry->forType($type);
        if (! $providers) {
            throw new TravelException(__('No provider is available for this search right now.'), 503);
        }

        $promises = [];
        $report = [];
        $started = [];
        foreach ($providers as $provider) {
            $key = $provider->key();
            $started[$key] = microtime(true);
            try {
                $promises[$key] = $search($provider)->then(function (array $entries) use ($key, $started, &$report) {
                    $report[$key]['ms'] = (int) ((microtime(true) - $started[$key]) * 1000);

                    return $entries;
                });
            } catch (Throwable $e) {
                report($e);
                $report[$key] = ['status' => 'error', 'count' => 0, 'error' => $e->getMessage()];
            }
        }

        $entries = [];
        foreach (Utils::settle($promises)->wait() as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $report[$key] = ['status' => 'ok', 'count' => count($result['value'])] + ($report[$key] ?? []);
                foreach ($result['value'] as $entry) {
                    $entries[$entry['offer']['id']] = $entry;
                }
            } else {
                report($result['reason']);
                $report[$key] = ['status' => 'error', 'count' => 0, 'error' => $this->reason($result['reason'])];
            }
        }

        $this->offers->putMany($entries);

        $offers = $this->dedupe(array_column($entries, 'offer'));
        $searchId = (string) Str::uuid();

        Cache::put($this->key($searchId), [
            'type' => $type->value,
            'query' => $query,
            'offers' => $offers,
            'providers' => $report,
        ], config('travel.search_ttl'));

        return ['search_id' => $searchId, 'query' => $query, 'offers' => $offers, 'providers' => $report];
    }

    /** @return array{type: string, query: array, offers: list<array>, providers: array} */
    public function find(string $searchId, ItemType $type): array
    {
        $search = Cache::get($this->key($searchId));
        if (! $search || $search['type'] !== $type->value) {
            throw new TravelException(__('This search has expired, please search again.'), 410);
        }

        return $search;
    }

    /**
     * Same `dedupe_key` from several providers → keep the cheapest, list the
     * others under `also_offered_by`.
     */
    private function dedupe(array $offers): array
    {
        $kept = [];
        foreach ($offers as $offer) {
            $key = $offer['dedupe_key'] ?? $offer['id'];
            if (! isset($kept[$key])) {
                $kept[$key] = $offer + ['also_offered_by' => []];

                continue;
            }

            $current = $kept[$key];
            $alternative = fn (array $o) => ['provider' => $o['provider'], 'id' => $o['id'], 'total_amount' => $o['total_amount']];
            if ($offer['total_amount'] < $current['total_amount']) {
                $others = [...$current['also_offered_by'], $alternative($current)];
                $kept[$key] = $offer + ['also_offered_by' => $others];
            } else {
                $kept[$key]['also_offered_by'][] = $alternative($offer);
            }
        }

        return array_values($kept);
    }

    private function reason(mixed $reason): string
    {
        return $reason instanceof Throwable ? $reason->getMessage() : (string) $reason;
    }

    private function key(string $searchId): string
    {
        return 'travel:search:'.$searchId;
    }
}
