<?php

namespace App\Services\Travel\Data;

final class ActivitySearchQuery
{
    public function __construct(
        public readonly string $cityId,
        public readonly ?string $cityName = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $query = null,
        public readonly ?string $date = null,
    ) {}

    public function toArray(): array
    {
        return [
            'city' => $this->cityId,
            'q' => $this->query,
            'date' => $this->date,
        ];
    }
}
