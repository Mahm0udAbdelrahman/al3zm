<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\StripeGateway;
use App\Services\Payments\Gateways\TapGateway;
use App\Services\Payments\Gateways\ThawaniGateway;
use App\Services\Travel\Exceptions\TravelException;
use Illuminate\Contracts\Container\Container;

class PaymentGatewayManager
{
    public const GATEWAYS = [
        'thawani' => ThawaniGateway::class,
        'tap' => TapGateway::class,
        'stripe' => StripeGateway::class,
        'fake' => FakeGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function get(string $key): PaymentGateway
    {
        $class = self::GATEWAYS[$key] ?? null;
        $gateway = $class ? $this->container->make($class) : null;
        if (! $gateway || ! $gateway->enabled()) {
            throw new TravelException(__('This payment method is not available.'), 422);
        }

        return $gateway;
    }

    /** Enabled gateways usable from [$country] (ISO-2), as the app lists them. */
    public function available(?string $country = null): array
    {
        $list = [];
        foreach (self::GATEWAYS as $key => $class) {
            $gateway = $this->container->make($class);
            $countries = array_map('strtoupper', config("payments.gateways.{$key}.countries", []));
            if (! $gateway->enabled() || ($country && $countries && ! in_array(strtoupper($country), $countries, true))) {
                continue;
            }
            $name = config("payments.gateways.{$key}.name");
            $list[] = [
                'key' => $key,
                'name' => is_array($name) ? ($name[app()->getLocale()] ?? $name['en']) : $name,
                'currencies' => $gateway->currencies(),
            ];
        }

        return $list;
    }
}
