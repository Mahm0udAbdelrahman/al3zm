<?php

namespace App\Services\Travel\Providers\BookingCom;

use App\Enums\ItemType;
use App\Models\OrderItem;
use App\Services\Travel\Contracts\StayProvider;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\Data\CancellationResult;
use App\Services\Travel\Data\Quote;
use App\Services\Travel\Data\StaySearchQuery;
use App\Services\Travel\Exceptions\TravelException;
use App\Services\Travel\Providers\HttpProvider;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\Shapes;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Booking.com Demand API v3 (affiliate partners):
 * accommodations/search (+ details) → orders/preview → orders/create.
 *
 * Booking through the API needs the account to be approved for it; until
 * then keep BOOKING_COM_ENABLED off. Field names follow the 3.1 reference —
 * check them against the sandbox when the account is live.
 */
class BookingComStayProvider extends HttpProvider implements StayProvider
{
    private const AMENITIES = [
        // Booking.com facility ids → the app's amenities.
        107 => 'wifi', 96 => 'wifi', 433 => 'pool', 301 => 'pool', 2 => 'parking', 46 => 'parking',
        11 => 'gym', 54 => 'spa', 17 => 'shuttle', 139 => 'shuttle', 999 => 'kitchen',
    ];

    public function key(): string
    {
        return 'booking';
    }

    protected function configKey(): string
    {
        return 'booking';
    }

    public function type(): ItemType
    {
        return ItemType::Stay;
    }

    public function enabled(): bool
    {
        return $this->config('stays') && filled($this->config('token')) && filled($this->config('affiliate_id'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withToken((string) $this->config('token'))
            ->withHeaders(['X-Affiliate-Id' => (string) $this->config('affiliate_id')])
            ->acceptJson()
            ->asJson()
            ->timeout($this->config('timeout'));
    }

    private function language(): string
    {
        return app()->getLocale() === 'ar' ? 'ar' : $this->config('language');
    }

    private function booker(): array
    {
        return ['country' => $this->config('booker_country'), 'platform' => 'mobile'];
    }

    private function text(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value[$this->language()] ?? $value['en-gb'] ?? reset($value) ?: '');
        }

        return (string) $value;
    }

    public function searchStays(StaySearchQuery $query): PromiseInterface
    {
        $currency = $this->pricing->currency();

        return $this->sendAsync('POST', '/accommodations/search', ['json' => [
            'booker' => $this->booker(),
            'checkin' => $query->checkIn,
            'checkout' => $query->checkOut,
            'coordinates' => [
                'latitude' => $query->latitude,
                'longitude' => $query->longitude,
                'radius' => $query->radiusKm ?? $this->config('radius_km'),
            ],
            'currency' => $currency,
            'guests' => [
                'number_of_adults' => $query->adults,
                'number_of_rooms' => $query->rooms,
                'children' => $query->childrenAges,
            ],
            'extras' => ['products'],
            'rows' => 100,
        ]])->then(function (Response $response) use ($query) {
            $results = collect($response->json('data', []))->keyBy('id');
            if ($results->isEmpty()) {
                return [];
            }

            return $this->sendAsync('POST', '/accommodations/details', ['json' => [
                'accommodations' => $results->keys()->all(),
                'extras' => ['description', 'facilities', 'photos', 'rooms'],
                'languages' => array_values(array_unique([$this->language(), 'en-gb'])),
            ]])->then(function (Response $details) use ($results, $query) {
                $entries = [];
                foreach ($details->json('data', []) as $accommodation) {
                    $result = $results->get($accommodation['id']);
                    if (! $result) {
                        continue;
                    }
                    try {
                        $entries[] = [
                            'offer' => $this->mapHotel($accommodation, $result, $query->toArray()),
                            'context' => ['accommodation' => $accommodation, 'result' => $result, 'query' => $query->toArray()],
                        ];
                    } catch (Throwable $e) {
                        report($e);
                    }
                }

                return $entries;
            });
        });
    }

    private function mapHotel(array $accommodation, array $result, array $query, bool $withRooms = false): array
    {
        $currency = data_get($result, 'currency.booker', $this->pricing->currency());
        $total = $this->pricing->sell((float) data_get($result, 'price.total', data_get($result, 'price.book', 0)), $currency, ItemType::Stay);
        $perNight = round($total / $query['nights'] / $query['rooms'], 3);
        $lat = data_get($accommodation, 'location.coordinates.latitude');
        $lng = data_get($accommodation, 'location.coordinates.longitude');
        $id = OfferRef::make($this->key(), (string) $accommodation['id']);
        $products = $result['products'] ?? [];
        $roomNames = collect($accommodation['rooms'] ?? [])->mapWithKeys(fn ($r) => [$r['id'] => $this->text($r['name'] ?? '')]);

        $rooms = $withRooms
            ? array_map(fn ($p) => $this->mapProduct($p, $accommodation['id'], $roomNames, $currency, $query), $products)
            : [Shapes::room(['id' => $id, 'name' => __('Cheapest room'), 'max_guests' => $query['adults'], 'price_per_night' => $perNight])];

        return Shapes::hotel([
            'id' => $id,
            'provider' => $this->key(),
            'name' => $this->text($accommodation['name'] ?? ''),
            'stars' => (int) data_get($accommodation, 'rating.stars', 0),
            'score' => (float) data_get($accommodation, 'rating.review_score', 0),
            'reviews' => (int) data_get($accommodation, 'rating.number_of_reviews', 0),
            'area' => $this->text(data_get($accommodation, 'location.address', '')),
            'address' => $this->text(data_get($accommodation, 'location.address', '')),
            'latitude' => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
            'distance_km' => Shapes::distanceKm($query['latitude'], $query['longitude'], $lat, $lng),
            'images' => array_values(array_filter(array_map(
                fn ($p) => data_get($p, 'url.large') ?? data_get($p, 'url.standard') ?? (is_string($p['url'] ?? null) ? $p['url'] : null),
                $accommodation['photos'] ?? [],
            ))),
            'description' => $this->text(data_get($accommodation, 'description.text', '')),
            'amenities' => array_values(array_unique(array_filter(array_map(
                fn ($f) => self::AMENITIES[$f['id'] ?? 0] ?? null,
                $accommodation['facilities'] ?? [],
            )))),
            'free_cancellation' => collect($products)->contains(fn ($p) => data_get($p, 'policies.cancellation.type') === 'free_cancellation'),
            'rooms' => $rooms,
            'check_in_time' => substr((string) data_get($accommodation, 'checkin_checkout_times.checkin_from', '15:00'), 0, 5),
            'check_out_time' => substr((string) data_get($accommodation, 'checkin_checkout_times.checkout_to', '12:00'), 0, 5),
            'currency' => $this->pricing->currency(),
            'total_amount' => $total,
            'price_from' => $perNight,
            'nights' => $query['nights'],
            'deep_link_url' => $result['deep_link_url'] ?? $result['url'] ?? null,
        ]);
    }

    private function mapProduct(array $product, int|string $accommodationId, $roomNames, string $currency, array $query): array
    {
        $total = $this->pricing->sell((float) data_get($product, 'price.total', data_get($product, 'price.book', 0)), $currency, ItemType::Stay);
        $meal = data_get($product, 'policies.meal_plan.plan', 'no_plan');
        $cancellation = data_get($product, 'policies.cancellation.type');

        return Shapes::room([
            'id' => OfferRef::make($this->key(), "{$accommodationId}|{$product['id']}"),
            'name' => $roomNames[$product['room'] ?? null] ?? __('Room'),
            'max_guests' => (int) ($product['number_of_adults'] ?? $query['adults']),
            'price_per_night' => round($total / $query['nights'] / $query['rooms'], 3),
            'total_amount' => $total,
            'breakfast_included' => in_array($meal, ['breakfast_included', 'half_board', 'full_board', 'all_inclusive'], true),
            'refundable' => $cancellation !== 'non_refundable',
            'free_cancellation_until' => $cancellation === 'free_cancellation'
                ? data_get($product, 'policies.cancellation.free_cancellation_until')
                : null,
        ]);
    }

    public function stayDetails(array $entry): array
    {
        $context = $entry['context'];
        $hotel = $this->mapHotel($context['accommodation'], $context['result'], $context['query'], withRooms: true);
        usort($hotel['rooms'], fn ($a, $b) => $a['total_amount'] <=> $b['total_amount']);

        $entries = [];
        foreach ($hotel['rooms'] as $room) {
            [$accommodationId, $productId] = explode('|', OfferRef::parse($room['id'])->raw, 2);
            $entries[$room['id']] = ['offer' => $room, 'context' => [
                'accommodation_id' => (int) $accommodationId,
                'product_id' => $productId,
                'hotel' => $hotel['name'],
                'query' => $context['query'],
            ]];
        }

        return ['hotel' => $hotel, 'entries' => $entries];
    }

    private function allocation(array $query): array
    {
        return ['number_of_adults' => (int) ceil($query['adults'] / max(1, $query['rooms'])), 'children' => $query['children_ages'] ?? []];
    }

    public function quote(array $entry, array $input): Quote
    {
        $context = $entry['context'];
        if (empty($context['product_id'])) {
            throw new TravelException(__('Pick a room first.'), 422);
        }
        $query = $context['query'];
        $currency = $this->pricing->currency();

        $preview = $this->send('POST', '/orders/preview', ['json' => [
            'booker' => $this->booker(),
            'currency' => $currency,
            'accommodation' => [
                'id' => $context['accommodation_id'],
                'checkin' => $query['check_in'],
                'checkout' => $query['check_out'],
                'products' => array_fill(0, $query['rooms'], [
                    'id' => $context['product_id'],
                    'allocation' => $this->allocation($query),
                ]),
            ],
        ]])->json('data');

        $amount = (float) (data_get($preview, 'accommodation.price.total.booker')
            ?? data_get($preview, 'accommodation.price.total')
            ?? $entry['offer']['total_amount']);

        return new Quote(
            amount: $amount,
            currency: $currency,
            title: $context['hotel'],
            summary: [
                'room' => $entry['offer'],
                'hotel' => $context['hotel'],
                'check_in' => $query['check_in'],
                'check_out' => $query['check_out'],
                'rooms' => $query['rooms'],
                'adults' => $query['adults'],
            ],
            data: [
                'order_token' => data_get($preview, 'order_token'),
                'accommodation_id' => $context['accommodation_id'],
                'product_id' => $context['product_id'],
                'rooms' => $query['rooms'],
                'allocation' => $this->allocation($query),
                'policies' => data_get($preview, 'accommodation.products.0.policies'),
            ],
        );
    }

    public function book(OrderItem $item): BookingResult
    {
        $t = $item->travelers;
        $lead = $t['guests'][0];
        $payment = array_filter([
            'method' => $this->config('payment.method'),
            'timing' => $this->config('payment.timing'),
            'card' => array_filter($this->config('payment.card', [])) ?: null,
        ]);

        try {
            $order = $this->send('POST', '/orders/create', ['json' => [
                'order_token' => $item->data('order_token'),
                'booker' => [
                    'email' => $t['email'],
                    'name' => ['first_name' => $lead['given_name'], 'last_name' => $lead['family_name']],
                    'telephone' => $t['phone_number'],
                    'language' => $this->language(),
                    'address' => ['country' => $this->config('booker_country')],
                ],
                'accommodation' => [
                    'label' => $item->order->reference,
                    'products' => array_fill(0, $item->data('rooms', 1), [
                        'id' => $item->data('product_id'),
                        'guests' => [['email' => $t['email'], 'name' => "{$lead['given_name']} {$lead['family_name']}"]],
                    ]),
                ],
                'payment' => $payment,
            ]])->json('data');

            $orderId = data_get($order, 'order_id') ?? data_get($order, 'id');

            return BookingResult::confirmed(
                (string) (data_get($order, 'accommodation.reservation') ?? $orderId),
                ['order_id' => $orderId, 'pincode' => data_get($order, 'accommodation.pincode')],
            );
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

        $result = $this->send('POST', '/orders/cancel', ['json' => ['order' => $orderId, 'reason' => 'Cancelled by the traveller']])->json('data');
        $free = data_get($item->provider_data, 'policies.cancellation.type') === 'free_cancellation';

        return new CancellationResult(
            cancelled: true,
            refundAmount: $free ? $item->provider_amount : (float) (data_get($result, 'refund.amount') ?? 0),
            currency: $item->provider_currency,
            data: ['cancellation' => $result],
        );
    }
}
