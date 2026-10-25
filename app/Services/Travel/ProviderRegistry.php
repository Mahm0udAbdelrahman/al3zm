<?php

namespace App\Services\Travel;

use App\Enums\ItemType;
use App\Services\Travel\Contracts\TravelProvider;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\Providers\BookingCom\BookingComStayProvider;
use App\Services\Travel\Providers\Duffel\DuffelFlightProvider;
use App\Services\Travel\Providers\Duffel\DuffelStayProvider;
use App\Services\Travel\Providers\GetYourGuide\GetYourGuideActivityProvider;
use App\Services\Travel\Providers\Wingie\WingieOneFlightProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Every provider, by vertical. Adding one is a class implementing the
 * vertical's interface plus a line here.
 */
class ProviderRegistry
{
    /** @var list<class-string<TravelProvider>> */
    public const PROVIDERS = [
        DuffelFlightProvider::class,
        WingieOneFlightProvider::class,
        DuffelStayProvider::class,
        BookingComStayProvider::class,
        GetYourGuideActivityProvider::class,
    ];

    /** @var list<TravelProvider>|null */
    private ?array $instances = null;

    public function __construct(private Container $container) {}

    /** @return list<TravelProvider> */
    public function all(): array
    {
        return $this->instances ??= array_map(fn (string $class) => $this->container->make($class), static::PROVIDERS);
    }

    /** @return list<TravelProvider> enabled providers of one vertical */
    public function forType(ItemType $type): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (TravelProvider $provider) => $provider->type() === $type && $provider->enabled(),
        ));
    }

    public function get(ItemType $type, string $key): TravelProvider
    {
        foreach ($this->all() as $provider) {
            if ($provider->type() === $type && $provider->key() === $key) {
                if (! $provider->enabled()) {
                    throw new TravelException(__('This provider is not available right now.'), 503);
                }

                return $provider;
            }
        }

        throw new TravelException(__('Unknown provider.'), 422);
    }

    /** @return array<string, array<string, bool>> what is on, for the status endpoint */
    public function status(): array
    {
        $status = [];
        foreach ($this->all() as $provider) {
            $status[$provider->type()->value][$provider->key()] = $provider->enabled();
        }

        return $status;
    }
}
