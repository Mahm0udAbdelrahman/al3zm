<?php

namespace App\Services\Travel\Support;

use Illuminate\Support\Str;

/**
 * The normalized shapes every provider maps into. They follow the mobile
 * app's models (FlightItinerary / FlightOffer, Hotel / RoomOption, Activity)
 * field for field, plus `provider`, `total_amount` and a `dedupe_key`.
 */
final class Shapes
{
    public static function airport(string $code, ?string $city = null, ?string $name = null, ?string $country = null, ?float $lat = null, ?float $lng = null): array
    {
        return [
            'code' => $code,
            'city' => $city ?? $code,
            'name' => $name ?? $code,
            'country' => $country ?? '',
            'latitude' => $lat ?? 0.0,
            'longitude' => $lng ?? 0.0,
        ];
    }

    public static function airline(string $code, ?string $name = null, ?string $logo = null): array
    {
        return [
            'code' => $code,
            'name' => $name ?? $code,
            // A stable brand-ish colour per airline (ARGB), for the badge.
            'color' => 0xFF000000 | (crc32($code) & 0x5F5F7F),
            'logo_url' => $logo,
        ];
    }

    /**
     * One itinerary. `$legs` are FlightOffer-shaped (one per leg of the route,
     * each with `flights` = the actual planes); prices are in the display
     * currency for all passengers.
     */
    public static function itinerary(
        string $id,
        string $provider,
        array $legs,
        float $base,
        float $taxes,
        float $total,
        string $currency,
        int $adults,
        int $children,
        array $extra = [],
    ): array {
        // The app prices a child at 75% of an adult: split so its sum matches.
        $perAdult = $base / max(1, $adults + 0.75 * $children);
        $taxPerPassenger = $taxes / max(1, $adults + $children);
        $count = count($legs);

        foreach ($legs as $i => &$leg) {
            $leg['price'] = Currency::round($perAdult / $count, $currency);
            $leg['taxes'] = Currency::round($taxPerPassenger / $count, $currency);
            $leg['currency'] = $currency;
        }
        unset($leg);

        $flights = array_merge(...array_column($legs, 'flights'));

        return [
            'id' => $id,
            'provider' => $provider,
            'segments' => $legs,
            'total_amount' => Currency::round($total, $currency),
            'base_amount' => Currency::round($base, $currency),
            'tax_amount' => Currency::round($taxes, $currency),
            'currency' => $currency,
            'stops' => max(array_column($legs, 'stops') ?: [0]),
            'duration_minutes' => array_sum(array_column($legs, 'duration_minutes')),
            'airline_codes' => array_values(array_unique(array_column(array_column($legs, 'airline'), 'code'))),
            'refundable' => ! in_array(false, array_column($legs, 'refundable'), true),
            'dedupe_key' => implode('|', array_map(fn ($f) => $f['carrier'].$f['number'].'@'.substr($f['depart_at'], 0, 16), $flights)),
        ] + $extra;
    }

    /** One leg: the planes flown between the leg's two airports. */
    public static function leg(string $id, array $flights, string $cabin, array $extra = []): array
    {
        $first = $flights[0];
        $last = $flights[count($flights) - 1];
        $layover = null;
        if (count($flights) > 1) {
            $layover = (int) ((strtotime($flights[1]['depart_at']) - strtotime($first['arrive_at'])) / 60);
        }

        return [
            'id' => $id,
            'airline' => $first['airline'],
            'flight_number' => $first['carrier'].' '.$first['number'],
            'from' => $first['from'],
            'to' => $last['to'],
            'depart_at' => $first['depart_at'],
            'arrive_at' => $last['arrive_at'],
            'duration_minutes' => $extra['duration_minutes']
                ?? (int) ((strtotime($last['arrive_at']) - strtotime($first['depart_at'])) / 60),
            'stopover' => count($flights) > 1 ? $first['to']['code'] : null,
            'layover_minutes' => $layover,
            'stops' => count($flights) - 1,
            'cabin' => $cabin,
            'price' => 0,
            'taxes' => 0,
            'extra_bag_price' => 0,
            'currency' => '',
            'cabin_bag_kg' => 7,
            'checked_bag_kg' => 0,
            'refundable' => false,
            'seats_left' => 9,
            'taken_seats' => [],
            'flights' => $flights,
        ] + $extra;
    }

    public static function hotel(array $hotel): array
    {
        $hotel += [
            'type' => 'hotel',
            'stars' => 0,
            'score' => 0.0,
            'reviews' => 0,
            'area' => '',
            'distance_km' => 0.0,
            'images' => [],
            'description' => '',
            'amenities' => [],
            'free_cancellation' => false,
            'rooms' => [],
            'check_in_time' => '15:00',
            'check_out_time' => '12:00',
            'latitude' => null,
            'longitude' => null,
            'address' => '',
        ];

        if (! $hotel['type'] || $hotel['type'] === 'hotel') {
            $hotel['type'] = self::stayType($hotel['name']);
        }

        // The same property from two providers: same name, ~100 m apart.
        $hotel['dedupe_key'] = Str::slug($hotel['name']).'@'.(
            $hotel['latitude'] !== null
                ? round($hotel['latitude'], 3).','.round($hotel['longitude'], 3)
                : Str::slug($hotel['area'])
        );

        return $hotel;
    }

    public static function room(array $room): array
    {
        return $room + [
            'beds' => '',
            'size_sqm' => 0,
            'max_guests' => 2,
            'breakfast_included' => false,
            'refundable' => false,
            'free_cancellation_until' => null,
        ];
    }

    public static function activity(array $activity): array
    {
        return $activity + [
            'category' => 'tours',
            'image_url' => '',
            'images' => [],
            'rating' => 0.0,
            'reviews' => 0,
            'duration_minutes' => 60,
            'free_cancellation' => false,
            'instant_confirmation' => true,
            'featured' => false,
            'description' => '',
            'highlights' => [],
            'meeting_point' => '',
            'dedupe_key' => Str::slug($activity['title']),
        ];
    }

    public static function stayType(string $name): string
    {
        $name = Str::lower($name);

        return match (true) {
            Str::contains($name, ['resort']) => 'resort',
            Str::contains($name, ['apartment', 'apartments', 'residence', 'suites', 'flat']) => 'apartment',
            default => 'hotel',
        };
    }

    public static function distanceKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): float
    {
        if ($lat1 === null || $lat2 === null) {
            return 0.0;
        }
        $rad = fn ($d) => $d * M_PI / 180;
        $a = sin($rad($lat2 - $lat1) / 2) ** 2
            + cos($rad($lat1)) * cos($rad($lat2)) * sin($rad($lng2 - $lng1) / 2) ** 2;

        return round(2 * 6371 * asin(min(1, sqrt($a))), 1);
    }

    /** ISO-8601 duration (PT2H30M, P1DT3H) → minutes. */
    public static function minutes(?string $iso): int
    {
        if (! $iso) {
            return 0;
        }
        try {
            $i = new \DateInterval($iso);

            return $i->d * 1440 + $i->h * 60 + $i->i;
        } catch (\Exception) {
            return 0;
        }
    }
}
