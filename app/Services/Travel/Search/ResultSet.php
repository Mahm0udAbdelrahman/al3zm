<?php

namespace App\Services\Travel\Search;

use App\Enums\ItemType;

/**
 * Filters, sorts and pages merged offers. Works on the normalized shapes, so
 * offers from every provider sort together.
 */
class ResultSet
{
    public const SORTS = [
        'flight' => ['price', 'duration', 'departure', 'arrival', 'best'],
        'stay' => ['price', 'score', 'stars', 'distance', 'best'],
        'activity' => ['price', 'rating', 'reviews', 'duration'],
    ];

    /**
     * @return array{items: list<array>, pagination: array}
     */
    public function apply(ItemType $type, array $offers, array $options): array
    {
        $offers = array_values(array_filter($offers, fn (array $offer) => $this->matches($type, $offer, $options)));
        $offers = $this->sort($type, $offers, $options['sort'] ?? 'price', $options['direction'] ?? 'asc');

        $perPage = min(max((int) ($options['per_page'] ?? 50), 1), 200);
        $total = count($offers);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max((int) ($options['page'] ?? 1), 1), $lastPage);

        return [
            'items' => array_slice($offers, ($page - 1) * $perPage, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function matches(ItemType $type, array $offer, array $f): bool
    {
        if (! empty($f['providers']) && ! in_array($offer['provider'], (array) $f['providers'], true)) {
            return false;
        }
        if (isset($f['max_price']) && $offer['total_amount'] > (float) $f['max_price']) {
            return false;
        }
        if (isset($f['min_price']) && $offer['total_amount'] < (float) $f['min_price']) {
            return false;
        }

        return match ($type) {
            ItemType::Flight => (! isset($f['max_stops']) || $offer['stops'] <= (int) $f['max_stops'])
                && (empty($f['airlines']) || array_intersect((array) $f['airlines'], $offer['airline_codes']))
                && (empty($f['refundable']) || $offer['refundable']),
            ItemType::Stay => (! isset($f['min_stars']) || $offer['stars'] >= (int) $f['min_stars'])
                && (! isset($f['min_score']) || $offer['score'] >= (float) $f['min_score'])
                && (empty($f['free_cancellation']) || $offer['free_cancellation'])
                && (empty($f['types']) || in_array($offer['type'], (array) $f['types'], true)),
            ItemType::Activity => (! isset($f['min_rating']) || $offer['rating'] >= (float) $f['min_rating'])
                && (empty($f['free_cancellation']) || $offer['free_cancellation'])
                && (empty($f['categories']) || in_array($offer['category'], (array) $f['categories'], true)),
        };
    }

    private function sort(ItemType $type, array $offers, string $sort, string $direction): array
    {
        $value = match ("{$type->value}.{$sort}") {
            'flight.duration' => fn ($o) => $o['duration_minutes'],
            'flight.departure' => fn ($o) => $o['segments'][0]['depart_at'] ?? '',
            'flight.arrival' => fn ($o) => end($o['segments'])['arrive_at'] ?? '',
            'flight.best' => $this->best($offers, 'total_amount', 'duration_minutes'),
            'stay.score' => fn ($o) => -$o['score'],
            'stay.stars' => fn ($o) => -$o['stars'],
            'stay.distance' => fn ($o) => $o['distance_km'],
            'stay.best' => $this->best($offers, 'total_amount', 'score', higherIsBetter: true),
            'activity.rating' => fn ($o) => -$o['rating'],
            'activity.reviews' => fn ($o) => -$o['reviews'],
            'activity.duration' => fn ($o) => $o['duration_minutes'],
            default => fn ($o) => $o['total_amount'],
        };

        usort($offers, fn ($a, $b) => $value($a) <=> $value($b) ?: $a['total_amount'] <=> $b['total_amount']);

        return $direction === 'desc' ? array_reverse($offers) : $offers;
    }

    /** Price and a second measure, each scaled 0–1 and weighted equally. */
    private function best(array $offers, string $price, string $other, bool $higherIsBetter = false): \Closure
    {
        $prices = array_column($offers, $price) ?: [0];
        $others = array_column($offers, $other) ?: [0];
        [$pMin, $pMax, $oMin, $oMax] = [min($prices), max($prices), min($others), max($others)];
        $scale = fn ($v, $min, $max) => $max > $min ? ($v - $min) / ($max - $min) : 0;

        return function ($o) use ($price, $other, $pMin, $pMax, $oMin, $oMax, $scale, $higherIsBetter) {
            $second = $scale($o[$other], $oMin, $oMax);

            return $scale($o[$price], $pMin, $pMax) + ($higherIsBetter ? 1 - $second : $second);
        };
    }
}
