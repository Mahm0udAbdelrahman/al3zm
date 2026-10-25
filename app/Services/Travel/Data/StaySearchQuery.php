<?php

namespace App\Services\Travel\Data;

final class StaySearchQuery
{
    public function __construct(
        public readonly string $cityId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly int $adults = 2,
        public readonly int $rooms = 1,
        /** @var list<int> */
        public readonly array $childrenAges = [],
        public readonly ?int $radiusKm = null,
    ) {}

    public function nights(): int
    {
        return max(1, (int) round((strtotime($this->checkOut) - strtotime($this->checkIn)) / 86400));
    }

    public function toArray(): array
    {
        return [
            'city' => $this->cityId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'adults' => $this->adults,
            'rooms' => $this->rooms,
            'children_ages' => $this->childrenAges,
            'nights' => $this->nights(),
        ];
    }
}
