<?php

namespace App\Services\Api\Travel;

use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\Providers\Duffel\DuffelFlightProvider;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Airports and cities: the configured favourites first, anything else looked
 * up live through Duffel's place suggestions.
 */
class CatalogService
{
    public function __construct(public DuffelFlightProvider $duffel) {}

    private function local(mixed $value): string
    {
        return is_array($value) ? ($value[app()->getLocale()] ?? $value['en'] ?? reset($value)) : (string) $value;
    }

    public function airports(?string $q = null): array
    {
        $airports = array_map(fn ($a) => [
            'code' => $a['code'],
            'city' => $this->local($a['city']),
            'name' => $this->local($a['name']),
            'country' => $a['country'],
            'latitude' => $a['latitude'],
            'longitude' => $a['longitude'],
        ], config('travel.airports'));

        if (! $q) {
            return $airports;
        }

        if ($this->duffel->enabled() && mb_strlen($q) >= 2) {
            return Cache::remember('travel:places:'.mb_strtolower($q), 86400, fn () => $this->duffel->places($q));
        }

        $q = mb_strtolower($q);

        return array_values(array_filter($airports, fn ($a) => str_contains(mb_strtolower("{$a['code']} {$a['city']} {$a['name']}"), $q)));
    }

    public function cities(?string $q = null): array
    {
        $cities = array_map(fn ($c) => [
            'id' => $c['id'],
            'name' => $this->local($c['name']),
            'country' => $c['country'],
            'latitude' => $c['latitude'],
            'longitude' => $c['longitude'],
        ], config('travel.cities'));

        if (! $q) {
            return $cities;
        }

        $needle = mb_strtolower($q);
        $matches = array_values(array_filter($cities, fn ($c) => str_contains(mb_strtolower("{$c['id']} {$c['name']}"), $needle)));
        if ($matches || ! $this->duffel->enabled()) {
            return $matches;
        }

        return array_values(array_map(fn ($p) => [
            'id' => $p['code'],
            'name' => $p['city'],
            'country' => $p['country'],
            'latitude' => $p['latitude'],
            'longitude' => $p['longitude'],
        ], array_filter($this->airports($q), fn ($p) => $p['latitude'] && $p['longitude'])));
    }

    /** A city by id (or IATA code) with its coordinates. */
    public function city(string $id): array
    {
        foreach ($this->cities() as $city) {
            if (strcasecmp($city['id'], $id) === 0) {
                return $city;
            }
        }

        try {
            $found = $this->cities($id);
            foreach ($found as $city) {
                if (strcasecmp($city['id'], $id) === 0) {
                    return $city;
                }
            }
            if (count($found) === 1) {
                return $found[0];
            }
        } catch (Throwable $e) {
            report($e);
        }

        throw new TravelException(__('Unknown city. Send latitude and longitude instead.'), 422);
    }
}
