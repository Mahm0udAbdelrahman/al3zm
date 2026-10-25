<?php

namespace App\Services\Travel\Providers\Duffel;

use App\Enums\ItemType;
use App\Models\OrderItem;
use App\Services\Travel\Contracts\FlightProvider;
use App\Services\Travel\Contracts\HoldsInventory;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\Data\CancellationResult;
use App\Services\Travel\Data\FlightSearchQuery;
use App\Services\Travel\Data\Quote;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\Support\Currency;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\Shapes;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Duffel flights: offer request → offer (price check) → order.
 *
 * Fares that allow it are held (an unpaid `hold` order) while the user pays,
 * then paid from the balance; the rest are booked as `instant` orders after
 * payment.
 */
class DuffelFlightProvider extends DuffelProvider implements FlightProvider, HoldsInventory
{
    private const CABINS = ['economy' => 'economy', 'premium' => 'premium_economy', 'business' => 'business', 'first' => 'first'];

    private const MAX_OFFERS = 300;

    public function type(): ItemType
    {
        return ItemType::Flight;
    }

    public function enabled(): bool
    {
        return $this->config('flights') && $this->configured();
    }

    public function searchFlights(FlightSearchQuery $query): PromiseInterface
    {
        $passengers = [
            ...array_fill(0, $query->adults, ['type' => 'adult']),
            ...array_fill(0, $query->children, ['age' => 8]),
            ...array_fill(0, $query->infants, ['age' => 1]),
        ];

        $body = array_filter([
            'slices' => array_map(fn ($leg) => [
                'origin' => $leg['from'],
                'destination' => $leg['to'],
                'departure_date' => $leg['date'],
            ], $query->legs),
            'passengers' => $passengers,
            'cabin_class' => self::CABINS[$query->cabin] ?? 'economy',
            'max_connections' => $query->maxConnections,
        ], fn ($v) => $v !== null);

        $uri = '/air/offer_requests?return_offers=true&supplier_timeout='.$this->config('supplier_timeout');

        return $this->sendAsync('POST', $uri, $this->body($body))
            ->then(fn (Response $response) => $this->mapOffers($response->json('data.offers', []), $query));
    }

    /** @return list<array{offer: array, context: array}> */
    private function mapOffers(array $offers, FlightSearchQuery $query): array
    {
        usort($offers, fn ($a, $b) => (float) $a['total_amount'] <=> (float) $b['total_amount']);

        $entries = [];
        foreach (array_slice($offers, 0, self::MAX_OFFERS) as $offer) {
            try {
                $entries[] = [
                    'offer' => $this->mapOffer($offer, $query->adults, $query->children),
                    'context' => [
                        'offer_id' => $offer['id'],
                        'passengers' => array_map(fn ($p) => ['id' => $p['id'], 'type' => $p['type'] ?? null, 'age' => $p['age'] ?? null], $offer['passengers']),
                        'query' => $query->toArray(),
                    ],
                ];
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $entries;
    }

    public function mapOffer(array $offer, int $adults, int $children): array
    {
        $currency = $offer['total_currency'];
        $sell = fn ($amount) => $this->pricing->sell((float) $amount, $currency, ItemType::Flight);
        $refundable = (bool) data_get($offer, 'conditions.refund_before_departure.allowed', false);
        $changeable = (bool) data_get($offer, 'conditions.change_before_departure.allowed', false);

        $legs = [];
        foreach ($offer['slices'] as $slice) {
            $flights = array_map(fn ($segment) => $this->mapSegment($segment), $slice['segments']);
            $baggages = data_get($slice, 'segments.0.passengers.0.baggages', []);
            $checked = collect($baggages)->firstWhere('type', 'checked')['quantity'] ?? 0;
            $carryOn = collect($baggages)->firstWhere('type', 'carry_on')['quantity'] ?? 1;

            $legs[] = Shapes::leg($slice['id'], $flights, $this->appCabin(data_get($slice, 'segments.0.passengers.0.cabin_class')), [
                'duration_minutes' => Shapes::minutes($slice['duration'] ?? null) ?: null,
                'fare_brand' => $slice['fare_brand_name'] ?? null,
                'checked_bag_kg' => $checked * 23,
                'cabin_bag_kg' => $carryOn ? 7 : 0,
                'refundable' => $refundable,
            ]);
        }
        foreach ($legs as &$leg) {
            $leg['duration_minutes'] ??= (int) ((strtotime($leg['arrive_at']) - strtotime($leg['depart_at'])) / 60);
        }
        unset($leg);

        $total = $sell($offer['total_amount']);
        $taxes = $offer['tax_amount'] !== null ? $sell($offer['tax_amount']) : 0.0;

        return Shapes::itinerary(
            id: OfferRef::make($this->key(), $offer['id']),
            provider: $this->key(),
            legs: $legs,
            base: $total - $taxes,
            taxes: $taxes,
            total: $total,
            currency: $this->pricing->currency(),
            adults: $adults,
            children: $children,
            extra: [
                'owner' => Shapes::airline(data_get($offer, 'owner.iata_code', ''), data_get($offer, 'owner.name'), data_get($offer, 'owner.logo_symbol_url')),
                'changeable' => $changeable,
                'expires_at' => $offer['expires_at'] ?? null,
                'requires_instant_payment' => (bool) data_get($offer, 'payment_requirements.requires_instant_payment', true),
                'emissions_kg' => isset($offer['total_emissions_kg']) ? (int) $offer['total_emissions_kg'] : null,
            ],
        );
    }

    private function mapSegment(array $segment): array
    {
        $place = fn (array $p) => Shapes::airport(
            $p['iata_code'],
            $p['city_name'] ?? data_get($p, 'city.name'),
            $p['name'] ?? null,
            $p['iata_country_code'] ?? null,
            isset($p['latitude']) ? (float) $p['latitude'] : null,
            isset($p['longitude']) ? (float) $p['longitude'] : null,
        );
        $carrier = $segment['marketing_carrier'];

        return [
            'id' => $segment['id'],
            'carrier' => $carrier['iata_code'],
            'number' => $segment['marketing_carrier_flight_number'],
            'airline' => Shapes::airline($carrier['iata_code'], $carrier['name'] ?? null, $carrier['logo_symbol_url'] ?? null),
            'operated_by' => data_get($segment, 'operating_carrier.name'),
            'from' => $place($segment['origin']),
            'to' => $place($segment['destination']),
            'depart_at' => $segment['departing_at'],
            'arrive_at' => $segment['arriving_at'],
            'duration_minutes' => Shapes::minutes($segment['duration'] ?? null),
            'aircraft' => data_get($segment, 'aircraft.name'),
        ];
    }

    private function appCabin(?string $duffel): string
    {
        return array_search($duffel, self::CABINS, true) ?: 'economy';
    }

    /** Extras the traveller can add: bags now, seats via seatMap(). */
    public function services(array $entry): array
    {
        $offer = $this->send('GET', '/air/offers/'.$entry['context']['offer_id'], ['query' => ['return_available_services' => 'true']])->json('data');

        return [
            'offer' => $this->mapOffer($offer, $entry['context']['query']['adults'], $entry['context']['query']['children']),
            'services' => array_map(fn ($s) => [
                'id' => $s['id'],
                'type' => $s['type'],
                'kind' => data_get($s, 'metadata.type'),
                'weight_kg' => data_get($s, 'metadata.maximum_weight_kg'),
                'quantity_max' => $s['maximum_quantity'] ?? 1,
                'passenger_ids' => $s['passenger_ids'] ?? [],
                'segment_ids' => $s['segment_ids'] ?? [],
                'amount' => $this->pricing->sell((float) $s['total_amount'], $s['total_currency'], ItemType::Flight),
                'currency' => $this->pricing->currency(),
            ], $offer['available_services'] ?? []),
            'passengers' => $entry['context']['passengers'],
        ];
    }

    /** Seats per segment; each free seat carries the service id to order it with. */
    public function seatMap(array $entry): array
    {
        $maps = $this->send('GET', '/air/seat_maps', ['query' => ['offer_id' => $entry['context']['offer_id']]])->json('data', []);

        return array_map(function ($map) {
            $seats = [];
            foreach (data_get($map, 'cabins', []) as $cabin) {
                foreach ($cabin['rows'] ?? [] as $row) {
                    foreach ($row['sections'] ?? [] as $section) {
                        foreach ($section['elements'] ?? [] as $element) {
                            if (($element['type'] ?? null) !== 'seat') {
                                continue;
                            }
                            $services = $element['available_services'] ?? [];
                            $seats[] = [
                                'designator' => $element['designator'],
                                'available' => (bool) $services,
                                'services' => array_map(fn ($s) => [
                                    'id' => $s['id'],
                                    'passenger_id' => $s['passenger_id'],
                                    'amount' => $this->pricing->sell((float) $s['total_amount'], $s['total_currency'], ItemType::Flight),
                                ], $services),
                            ];
                        }
                    }
                }
            }

            return [
                'segment_id' => $map['segment_id'],
                'slice_id' => $map['slice_id'] ?? null,
                'taken_seats' => array_values(array_column(array_filter($seats, fn ($s) => ! $s['available']), 'designator')),
                'seats' => $seats,
                'currency' => $this->pricing->currency(),
            ];
        }, $maps);
    }

    public function quote(array $entry, array $input): Quote
    {
        $context = $entry['context'];
        $offer = $this->send('GET', '/air/offers/'.$context['offer_id'], ['query' => ['return_available_services' => 'true']])->json('data');

        $passengers = $this->passengers($offer['passengers'], $input['passengers'] ?? []);

        // Paid extras (bags, seats) by service id.
        $available = collect($offer['available_services'] ?? [])->keyBy('id');
        $services = [];
        $amount = (float) $offer['total_amount'];
        $extras = 0.0;
        foreach ($input['services'] ?? [] as $service) {
            $known = $available->get($service['id']);
            if (! $known) {
                throw new TravelException(__('An extra you picked is no longer available.'), 409);
            }
            $quantity = (int) ($service['quantity'] ?? 1);
            $services[] = ['id' => $service['id'], 'quantity' => $quantity];
            $extras += (float) $known['total_amount'] * $quantity;
        }
        $amount += $extras;

        $mapped = $this->mapOffer($offer, $context['query']['adults'], $context['query']['children']);
        $first = $mapped['segments'][0];
        $last = end($mapped['segments']);

        return new Quote(
            amount: $amount,
            currency: $offer['total_currency'],
            title: "{$first['from']['code']} → {$last['to']['code']}",
            summary: [
                'itinerary' => $mapped,
                'passengers' => count($passengers),
            ],
            data: [
                'offer_id' => $offer['id'],
                'passengers' => $passengers,
                'services' => $services,
                'requires_instant_payment' => (bool) data_get($offer, 'payment_requirements.requires_instant_payment', true),
            ],
            expiresAt: $offer['expires_at'] ?? null,
            extras: $extras,
        );
    }

    /**
     * Pairs the travellers the app sent with Duffel's passenger ids, by type
     * and order; each infant travels on an adult's lap.
     */
    private function passengers(array $duffelPassengers, array $travellers): array
    {
        $typeOf = fn (array $p) => $p['type'] ?? (($p['age'] ?? 18) < 2 ? 'infant_without_seat' : (($p['age'] ?? 18) < 18 ? 'child' : 'adult'));
        $slots = [];
        foreach ($duffelPassengers as $p) {
            $slots[match ($typeOf($p)) {
                'adult' => 'adult',
                'infant_without_seat' => 'infant',
                default => 'child',
            }][] = $p['id'];
        }

        $used = [];
        $mapped = [];
        foreach ($travellers as $traveller) {
            $type = $traveller['type'] ?? 'adult';
            $id = $slots[$type][$used[$type] ?? 0] ?? null;
            if (! $id) {
                throw new TravelException(__('The travellers do not match the search (adults, children, infants).'), 422);
            }
            $used[$type] = ($used[$type] ?? 0) + 1;

            $mapped[] = array_filter([
                'id' => $id,
                'type' => $type,
                'title' => $traveller['title'] ?? ($traveller['gender'] === 'f' ? 'ms' : 'mr'),
                'gender' => $traveller['gender'],
                'given_name' => $traveller['given_name'],
                'family_name' => $traveller['family_name'],
                'born_on' => $traveller['born_on'],
                'email' => $traveller['email'] ?? null,
                'phone_number' => $this->phone($traveller['phone_number'] ?? null),
                'identity_documents' => isset($traveller['passport_number']) ? [[
                    'type' => 'passport',
                    'unique_identifier' => $traveller['passport_number'],
                    'issuing_country_code' => $traveller['passport_country'] ?? null,
                    'expires_on' => $traveller['passport_expires_on'] ?? null,
                ]] : null,
            ], fn ($v) => $v !== null);
        }

        foreach ($slots as $type => $ids) {
            if (count($ids) !== ($used[$type] ?? 0)) {
                throw new TravelException(__('The travellers do not match the search (adults, children, infants).'), 422);
            }
        }

        // Each infant is attached to one adult.
        $adults = array_keys(array_filter($mapped, fn ($p) => $p['type'] === 'adult'));
        foreach (array_values(array_filter($mapped, fn ($p) => $p['type'] === 'infant')) as $i => $infant) {
            $mapped[$adults[$i]]['infant_passenger_id'] = $infant['id'];
        }

        return array_map(function ($p) {
            unset($p['type']);

            return $p;
        }, $mapped);
    }

    public function canHold(OrderItem $item): bool
    {
        // Paid extras cannot ride on an unpaid hold order.
        return ! $item->data('requires_instant_payment', true) && empty($item->data('services'));
    }

    public function hold(OrderItem $item): BookingResult
    {
        $order = $this->send('POST', '/air/orders', $this->body([
            'type' => 'hold',
            'selected_offers' => [$item->data('offer_id')],
            'passengers' => $item->data('passengers'),
        ]))->json('data');

        return BookingResult::held($order['booking_reference'] ?? null, data_get($order, 'payment_status.payment_required_by'), [
            'order_id' => $order['id'],
        ]);
    }

    public function release(OrderItem $item): void
    {
        if ($orderId = $item->data('order_id')) {
            $this->cancelOrder($orderId);
        }
    }

    public function book(OrderItem $item): BookingResult
    {
        try {
            // Held: pay the hold order's current total from the balance.
            if ($orderId = $item->data('order_id')) {
                $order = $this->send('GET', "/air/orders/{$orderId}")->json('data');
                $this->send('POST', '/air/payments', $this->body([
                    'order_id' => $orderId,
                    'payment' => ['type' => 'balance', 'amount' => $order['total_amount'], 'currency' => $order['total_currency']],
                ]));

                return BookingResult::confirmed($order['booking_reference'], ['order_id' => $orderId]);
            }

            $offer = $this->send('GET', '/air/offers/'.$item->data('offer_id'))->json('data');
            $amount = (float) $offer['total_amount'];
            foreach ($item->data('services', []) as $service) {
                $amount += (float) (collect($offer['available_services'] ?? [])->firstWhere('id', $service['id'])['total_amount'] ?? 0) * $service['quantity'];
            }

            $order = $this->send('POST', '/air/orders', $this->body(array_filter([
                'type' => 'instant',
                'selected_offers' => [$offer['id']],
                'passengers' => $item->data('passengers'),
                'services' => $item->data('services') ?: null,
                'payments' => [[
                    'type' => 'balance',
                    'currency' => $offer['total_currency'],
                    'amount' => Currency::format($amount, $offer['total_currency']),
                ]],
            ])))->json('data');

            return BookingResult::confirmed($order['booking_reference'], ['order_id' => $order['id']]);
        } catch (Throwable $e) {
            report($e);

            return BookingResult::failed($e->getMessage());
        }
    }

    public function cancel(OrderItem $item): CancellationResult
    {
        $orderId = $item->data('order_id');
        if (! $orderId) {
            return new CancellationResult(false, message: __('Nothing to cancel.'));
        }

        $cancellation = $this->cancelOrder($orderId);

        return new CancellationResult(
            cancelled: true,
            refundAmount: isset($cancellation['refund_amount']) ? (float) $cancellation['refund_amount'] : null,
            currency: $cancellation['refund_currency'] ?? null,
            data: ['cancellation_id' => $cancellation['id'] ?? null],
        );
    }

    private function cancelOrder(string $orderId): array
    {
        $cancellation = $this->send('POST', '/air/order_cancellations', $this->body(['order_id' => $orderId]))->json('data');
        $this->send('POST', "/air/order_cancellations/{$cancellation['id']}/actions/confirm");

        return $cancellation;
    }

    /** Airport and city suggestions for a typed query. */
    public function places(string $query): array
    {
        return array_map(fn ($place) => Shapes::airport(
            $place['iata_code'] ?? $place['iata_city_code'] ?? '',
            $place['city_name'] ?? $place['name'] ?? null,
            $place['name'] ?? null,
            $place['iata_country_code'] ?? null,
            isset($place['latitude']) ? (float) $place['latitude'] : null,
            isset($place['longitude']) ? (float) $place['longitude'] : null,
        ) + ['type' => $place['type'] ?? 'airport'], $this->send('GET', '/places/suggestions', ['query' => ['query' => $query]])->json('data', []));
    }
}
