<?php

namespace App\Services\Travel\Providers\GetYourGuide;

use App\Enums\ItemType;
use App\Models\OrderItem;
use App\Services\Travel\Contracts\ActivityProvider;
use App\Services\Travel\Contracts\HoldsInventory;
use App\Services\Travel\Data\ActivitySearchQuery;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\Data\CancellationResult;
use App\Services\Travel\Data\Quote;
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
 * GetYourGuide Partner API: tours → options → availabilities/pricings →
 * booking (reserves the slot) → cart (confirms it).
 *
 * The reservation is our hold: it is made when the order is created and
 * confirmed through a cart once the user has paid.
 */
class GetYourGuideActivityProvider extends HttpProvider implements ActivityProvider, HoldsInventory
{
    private const CATEGORIES = [
        'cruise' => 'cruises', 'boat' => 'cruises', 'adventure' => 'adventure', 'safari' => 'adventure',
        'desert' => 'adventure', 'diving' => 'adventure', 'ticket' => 'attractions', 'museum' => 'attractions',
        'attraction' => 'attractions', 'theme park' => 'attractions',
    ];

    public function key(): string
    {
        return 'gyg';
    }

    protected function configKey(): string
    {
        return 'getyourguide';
    }

    public function type(): ItemType
    {
        return ItemType::Activity;
    }

    public function enabled(): bool
    {
        return $this->config('activities') && filled($this->config('token'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withHeaders(['X-ACCESS-TOKEN' => (string) $this->config('token')])
            ->withQueryParameters([
                'cnt_language' => app()->getLocale() === 'ar' ? 'ar' : 'en',
                'currency' => strtolower($this->config('currency')),
            ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->config('timeout'));
    }

    private function baseData(): array
    {
        return [
            'cnt_language' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'currency' => strtolower($this->config('currency')),
        ];
    }

    public function searchActivities(ActivitySearchQuery $query): PromiseInterface
    {
        $params = ['limit' => 60, 'sortfield' => 'popularity', 'sortdirection' => 'DESC'];
        if ($query->query) {
            $params['q'] = $query->query;
        } elseif ($query->latitude !== null) {
            $params['coordinates'] = [$query->latitude, $query->longitude, $this->config('radius_km')];
        } else {
            $params['q'] = $query->cityName ?? $query->cityId;
        }
        if ($query->date) {
            $params['date'] = ["{$query->date}T00:00:00", "{$query->date}T23:59:59"];
        }

        return $this->sendAsync('GET', '/tours', ['query' => $this->query($params)])
            ->then(function (Response $response) use ($query) {
                $entries = [];
                foreach ($response->json('data.tours', []) as $tour) {
                    try {
                        $entries[] = [
                            'offer' => $this->mapTour($tour, $query->cityId),
                            'context' => ['tour_id' => $tour['tour_id'], 'city' => $query->cityId],
                        ];
                    } catch (Throwable $e) {
                        report($e);
                    }
                }

                return $entries;
            });
    }

    /** GetYourGuide wants repeated `key[]=` params for arrays. */
    private function query(array $params): string
    {
        return preg_replace('/%5B\d+%5D/', '%5B%5D', http_build_query($params));
    }

    private function mapTour(array $tour, string $cityId, bool $full = false): array
    {
        $price = $this->pricing->sell((float) data_get($tour, 'price.values.amount', 0), $this->config('currency'), ItemType::Activity);
        $pictures = array_map(fn ($p) => str_replace('[format_id]', $this->config('image_format'), $p['url'] ?? ''), $tour['pictures'] ?? []);
        $categoryText = strtolower(implode(' ', array_column($tour['categories'] ?? [], 'name')).' '.$tour['title']);
        $category = 'tours';
        foreach (self::CATEGORIES as $needle => $mapped) {
            if (str_contains($categoryText, $needle)) {
                $category = $mapped;
                break;
            }
        }

        return Shapes::activity([
            'id' => OfferRef::make($this->key(), (string) $tour['tour_id']),
            'provider' => $this->key(),
            'city_id' => $cityId,
            'title' => $tour['title'],
            'category' => $category,
            'image_url' => $pictures[0] ?? '',
            'images' => $pictures,
            'rating' => round((float) ($tour['overall_rating'] ?? 0), 1),
            'reviews' => (int) ($tour['number_of_ratings'] ?? 0),
            'duration_minutes' => $this->duration($tour['durations'][0] ?? null),
            'price' => $price,
            'total_amount' => $price,
            'currency' => $this->pricing->currency(),
            'free_cancellation' => (bool) ($tour['free_cancellation'] ?? $tour['has_free_cancellation'] ?? str_contains(strtolower($tour['cancellation_policy_text'] ?? ''), 'free')),
            'instant_confirmation' => (bool) ($tour['instant_confirmation'] ?? true),
            'featured' => (bool) ($tour['bestseller'] ?? false),
            'description' => $full ? strip_tags($tour['description'] ?? $tour['abstract'] ?? '') : ($tour['abstract'] ?? ''),
            'highlights' => $full ? array_values((array) ($tour['highlights'] ?? [])) : [],
            'meeting_point' => (string) data_get($tour, 'meeting_point.description', $tour['meeting_point'] ?? ''),
            'url' => $tour['url'] ?? null,
        ]);
    }

    private function duration(?array $duration): int
    {
        if (! $duration) {
            return 60;
        }
        $value = (float) ($duration['duration'] ?? 1);

        return (int) match ($duration['unit'] ?? 'hour') {
            'minute' => $value,
            'day' => $value * 1440,
            default => $value * 60,
        };
    }

    public function activityDetails(array $entry): array
    {
        $tourId = $entry['context']['tour_id'];
        $tour = $this->send('GET', "/tours/{$tourId}")->json('data.tours.0');
        $options = $this->send('GET', "/tours/{$tourId}/options")->json('data.tour_options', []);

        return $this->mapTour($tour, $entry['context']['city'], full: true) + [
            'options' => array_map(fn ($o) => [
                'id' => (string) $o['option_id'],
                'title' => $o['title'] ?? '',
                'description' => strip_tags($o['description'] ?? ''),
                'duration_minutes' => $this->duration(['duration' => $o['duration'] ?? 1, 'unit' => $o['duration_unit'] ?? 'hour']),
                'meeting_point' => (string) data_get($o, 'meeting_point.description', $o['meeting_point'] ?? ''),
            ], $options),
        ];
    }

    public function availability(array $entry, string $from, string $to): array
    {
        $tourId = $entry['context']['tour_id'];
        $options = $this->send('GET', "/tours/{$tourId}/options")->json('data.tour_options', []);
        $dates = $this->query(['date' => ["{$from}T00:00:00", "{$to}T23:59:59"]]);

        $slots = [];
        $entries = [];
        foreach ($options as $option) {
            $optionId = (string) $option['option_id'];
            $pricings = collect($this->send('GET', "/options/{$optionId}/pricings")->json('data.pricing', []))->keyBy('pricing_id');
            $availabilities = $this->send('GET', "/options/{$optionId}/availabilities?{$dates}")->json('data.availabilities', []);

            foreach ($availabilities as $availability) {
                $pricing = $pricings->get($availability['pricing_id'] ?? null) ?? $pricings->first();
                $categories = array_map(fn ($c) => [
                    'id' => (string) $c['id'],
                    'name' => $c['name'] ?? '',
                    'min_age' => $c['min_age'] ?? null,
                    'max_age' => $c['max_age'] ?? null,
                    'price' => $this->pricing->sell((float) data_get($c, 'scale.0.retail_price', 0), $this->config('currency'), ItemType::Activity),
                    'scale' => $c['scale'] ?? [],
                ], $pricing['categories'] ?? []);

                $id = OfferRef::make($this->key(), "{$tourId}|{$optionId}|{$availability['start_time']}");
                $slot = [
                    'id' => $id,
                    'option_id' => $optionId,
                    'option_title' => $option['title'] ?? '',
                    'start_time' => $availability['start_time'],
                    'vacancies' => $availability['vacancies'] ?? null,
                    'categories' => array_map(fn ($c) => array_diff_key($c, ['scale' => 1]), $categories),
                    'currency' => $this->pricing->currency(),
                ];
                $slots[] = $slot;
                $entries[$id] = ['offer' => $slot, 'context' => [
                    'tour_id' => $tourId,
                    'option_id' => $optionId,
                    'start_time' => $availability['start_time'],
                    'categories' => $categories,
                    'title' => $entry['offer']['title'],
                ]];
            }
        }

        return ['slots' => $slots, 'entries' => $entries];
    }

    public function quote(array $entry, array $input): Quote
    {
        $context = $entry['context'];
        if (empty($context['option_id'])) {
            throw new TravelException(__('Pick a date and time first.'), 422);
        }

        $categories = collect($context['categories'])->keyBy('id');
        $participants = [];
        $amount = 0.0;
        foreach ($input['participants'] ?? [] as $p) {
            $category = $categories->get((string) $p['category_id']);
            if (! $category) {
                throw new TravelException(__('Unknown participant category.'), 422);
            }
            $count = (int) $p['quantity'];
            // Group pricing: the scale step matching the head count.
            $step = collect($category['scale'])->first(fn ($s) => $count >= ($s['min_participants'] ?? 1) && $count <= ($s['max_participants'] ?? PHP_INT_MAX))
                ?? ($category['scale'][0] ?? ['retail_price' => 0]);
            $amount += (float) $step['retail_price'] * (($step['type'] ?? null) === 'group' ? 1 : $count);
            $participants[] = ['category_id' => (int) $category['id'], 'number_of_participants' => $count];
        }
        if (! $participants) {
            throw new TravelException(__('Add at least one participant.'), 422);
        }

        return new Quote(
            amount: $amount,
            currency: strtoupper($this->config('currency')),
            title: $context['title'],
            summary: ['start_time' => $context['start_time'], 'option' => $entry['offer']['option_title'] ?? '', 'participants' => $input['participants']],
            data: [
                'tour_id' => $context['tour_id'],
                'option_id' => $context['option_id'],
                'start_time' => $context['start_time'],
                'participants' => $participants,
                'price' => $amount,
            ],
        );
    }

    public function canHold(OrderItem $item): bool
    {
        return true;
    }

    public function hold(OrderItem $item): BookingResult
    {
        $booking = $this->send('POST', '/bookings', ['json' => [
            'base_data' => $this->baseData(),
            'data' => ['booking' => [
                'tour_id' => (int) $item->data('tour_id'),
                'option_id' => (int) $item->data('option_id'),
                'price' => $item->data('price'),
                'bookable' => [
                    'datetime' => $item->data('start_time'),
                    'categories' => $item->data('participants'),
                ],
            ]],
        ]])->json('data.bookings');

        return BookingResult::held(null, now()->addMinutes(config('travel.order_ttl'))->toIso8601String(), [
            'booking_hash' => $booking['booking_hash'],
        ]);
    }

    public function release(OrderItem $item): void
    {
        if ($hash = $item->data('booking_hash')) {
            $this->send('DELETE', "/bookings/{$hash}");
        }
    }

    public function book(OrderItem $item): BookingResult
    {
        try {
            $hash = $item->data('booking_hash') ?? $this->hold($item)->data['booking_hash'];
            $t = $item->travelers;
            $person = [
                'salutation_code' => ($t['gender'] ?? 'm') === 'f' ? 'f' : 'm',
                'first_name' => $t['given_name'],
                'last_name' => $t['family_name'],
                'email' => $t['email'],
                'phone' => $t['phone_number'],
            ];

            $cart = $this->send('POST', '/carts', ['json' => [
                'base_data' => $this->baseData(),
                'data' => ['cart' => [
                    'bookings' => [['booking_hash' => $hash]],
                    'billing' => $person + ['is_company' => false, 'country_code' => $t['country'] ?? 'OM'],
                    'traveler' => $person,
                    'payment' => ['type' => $this->config('payment_type')],
                ]],
            ]])->json('data.cart');

            $booking = data_get($cart, 'bookings.0', []);

            return BookingResult::confirmed(
                (string) ($booking['booking_reference'] ?? $booking['booking_id'] ?? $cart['cart_hash'] ?? $hash),
                ['booking_hash' => $hash, 'booking_id' => $booking['booking_id'] ?? null, 'cart_hash' => $cart['cart_hash'] ?? null, 'ticket_url' => $booking['ticket']['ticket_url'] ?? null],
            );
        } catch (Throwable $e) {
            report($e);

            return BookingResult::failed($e->getMessage());
        }
    }

    public function cancel(OrderItem $item): CancellationResult
    {
        $hash = $item->data('booking_hash');
        if (! $hash) {
            return new CancellationResult(false, message: __('Nothing to cancel.'));
        }

        $this->send('DELETE', "/bookings/{$hash}");

        // GetYourGuide refunds in full inside the free-cancellation window.
        return new CancellationResult(true, $item->summary['free_cancellation'] ?? true ? $item->provider_amount : 0.0, $item->provider_currency);
    }
}
