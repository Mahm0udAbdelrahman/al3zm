<?php

namespace App\Services\Travel\Data;

/**
 * A flight search as the app sends it: every leg of the route (one-way,
 * round trip already expanded, or multi-city), passengers and cabin.
 */
final class FlightSearchQuery
{
    /**
     * @param  list<array{from: string, to: string, date: string}>  $legs
     */
    public function __construct(
        public readonly array $legs,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly int $infants = 0,
        public readonly string $cabin = 'economy',
        public readonly ?int $maxConnections = null,
        public readonly string $tripType = 'one_way',
    ) {}

    public static function fromArray(array $data): self
    {
        $legs = array_map(fn (array $leg) => [
            'from' => strtoupper($leg['from']),
            'to' => strtoupper($leg['to']),
            'date' => substr($leg['date'], 0, 10),
        ], $data['legs']);

        // A round trip may come as one leg plus a return date.
        if (($data['trip_type'] ?? null) === 'roundTrip' && count($legs) === 1 && ! empty($data['return_date'])) {
            $legs[] = ['from' => $legs[0]['to'], 'to' => $legs[0]['from'], 'date' => substr($data['return_date'], 0, 10)];
        }

        return new self(
            legs: $legs,
            adults: (int) ($data['adults'] ?? 1),
            children: (int) ($data['children'] ?? 0),
            infants: (int) ($data['infants'] ?? 0),
            cabin: $data['cabin'] ?? 'economy',
            maxConnections: isset($data['max_connections']) ? (int) $data['max_connections'] : null,
            tripType: $data['trip_type'] ?? 'oneWay',
        );
    }

    public function passengers(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    public function toArray(): array
    {
        return [
            'trip_type' => $this->tripType,
            'legs' => $this->legs,
            'adults' => $this->adults,
            'children' => $this->children,
            'infants' => $this->infants,
            'cabin' => $this->cabin,
            'max_connections' => $this->maxConnections,
        ];
    }
}
