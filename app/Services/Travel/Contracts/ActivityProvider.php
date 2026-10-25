<?php

namespace App\Services\Travel\Contracts;

use App\Services\Travel\Data\ActivitySearchQuery;
use GuzzleHttp\Promise\PromiseInterface;

interface ActivityProvider extends TravelProvider
{
    /**
     * Resolves to a list of `['offer' => activity, 'context' => […]]`.
     */
    public function searchActivities(ActivitySearchQuery $query): PromiseInterface;

    /** Full description, highlights, options. */
    public function activityDetails(array $entry): array;

    /**
     * Bookable slots between two dates, one offer id per option and start time.
     *
     * @return array{slots: list<array>, entries: array<string, array{offer: array, context: array}>}
     */
    public function availability(array $entry, string $from, string $to): array;
}
