<?php

namespace App\Services\Travel\Support;

use App\Services\Travel\Exceptions\TravelException;

/**
 * The id the app sees for anything bookable: the provider plus the
 * provider's own id, URL-safe — e.g. `duffel-b2ZmXzAwMDA`. Whatever endpoint
 * receives it knows which provider to send it back to.
 */
final class OfferRef
{
    public function __construct(public readonly string $provider, public readonly string $raw) {}

    public static function make(string $provider, string $raw): string
    {
        return $provider.'-'.rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function parse(string $ref): self
    {
        [$provider, $encoded] = array_pad(explode('-', $ref, 2), 2, null);
        $raw = $encoded === null ? false : base64_decode(strtr($encoded, '-_', '+/'), true);

        if (! $provider || $raw === false || $raw === '') {
            throw new TravelException(__('Invalid offer id.'), 422);
        }

        return new self($provider, $raw);
    }
}
