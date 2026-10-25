<?php

namespace App\Services\Travel\Providers\Duffel;

use App\Enums\ItemType;
use App\Models\OrderItem;
use App\Services\Travel\Contracts\StayProvider;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\Data\CancellationResult;
use App\Services\Travel\Data\Quote;
use App\Services\Travel\Data\StaySearchQuery;
use App\Services\Travel\Exceptions\ProviderException;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\Shapes;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Duffel Stays: search → all rates of one hotel → quote → booking.
 */
class DuffelStayProvider extends DuffelProvider implements StayProvider
{
    private const AMENITIES = ['wifi' => 'wifi', 'pool' => 'pool', 'parking' => 'parking', 'gym' => 'gym', 'spa' => 'spa', 'restaurant' => 'breakfast'];

    public function type(): ItemType
    {
        return ItemType::Stay;
    }

    public function enabled(): bool
    {
        return $this->config('stays') && $this->configured();
    }

    public function searchStays(StaySearchQuery $query): PromiseInterface
    {
        $guests = [
            ...array_fill(0, $query->adults, ['type' => 'adult']),
            ...array_map(fn ($age) => ['type' => 'child', 'age' => $age], $query->childrenAges),
        ];

        return $this->sendAsync('POST', '/stays/search', $this->body([
            'rooms' => $query->rooms,
            'guests' => $guests,
            'check_in_date' => $query->checkIn,
            'check_out_date' => $query->checkOut,
            'location' => [
                'radius' => $query->radiusKm ?? $this->config('stays_radius_km'),
                'geographic_coordinates' => ['latitude' => $query->latitude, 'longitude' => $query->longitude],
            ],
        ]))->then(function (Response $response) use ($query) {
            $entries = [];
            foreach ($response->json('data.results', []) as $result) {
                try {
                    $entries[] = [
                        'offer' => $this->mapResult($result, $query),
                        'context' => ['search_result_id' => $result['id'], 'query' => $query->toArray()],
                    ];
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return $entries;
        });
    }

    private function mapResult(array $result, StaySearchQuery $query): array
    {
        $accommodation = $result['accommodation'];
        $total = $this->pricing->sell((float) $result['cheapest_rate_total_amount'], $result['cheapest_rate_currency'], ItemType::Stay);
        $perNight = round($total / $query->nights() / $query->rooms, 3);
        $id = OfferRef::make($this->key(), $result['id']);

        return $this->hotel($accommodation, $query, [
            'id' => $id,
            'total_amount' => $total,
            'price_from' => $perNight,
            // The cheapest rate stands in for the rooms until the hotel is opened.
            'rooms' => [Shapes::room([
                'id' => $id,
                'name' => __('Cheapest room'),
                'max_guests' => $query->adults,
                'price_per_night' => $perNight,
            ])],
        ]);
    }

    private function hotel(array $accommodation, StaySearchQuery|array $query, array $extra): array
    {
        $query = is_array($query) ? $query : $query->toArray();
        $lat = data_get($accommodation, 'location.geographic_coordinates.latitude');
        $lng = data_get($accommodation, 'location.geographic_coordinates.longitude');
        $amenityTypes = array_column($accommodation['amenities'] ?? [], 'type');

        return Shapes::hotel([
            'provider' => $this->key(),
            'name' => $accommodation['name'],
            'stars' => (int) ($accommodation['rating'] ?? 0),
            'score' => (float) ($accommodation['review_score'] ?? 0),
            'reviews' => (int) ($accommodation['review_count'] ?? 0),
            'area' => data_get($accommodation, 'location.address.city_name', ''),
            'address' => trim(data_get($accommodation, 'location.address.line_one', '').', '.data_get($accommodation, 'location.address.city_name', ''), ', '),
            'latitude' => $lat !== null ? (float) $lat : null,
            'longitude' => $lng !== null ? (float) $lng : null,
            'distance_km' => Shapes::distanceKm($query['latitude'], $query['longitude'], $lat, $lng),
            'images' => array_values(array_filter(array_column($accommodation['photos'] ?? [], 'url'))),
            'description' => $accommodation['description'] ?? '',
            'amenities' => array_values(array_unique(array_filter(array_map(fn ($t) => self::AMENITIES[$t] ?? null, $amenityTypes)))),
            'facilities' => array_column($accommodation['amenities'] ?? [], 'description'),
            'check_in_time' => substr(data_get($accommodation, 'check_in_information.check_in_after_time', '15:00'), 0, 5),
            'check_out_time' => substr(data_get($accommodation, 'check_in_information.check_out_before_time', '12:00'), 0, 5),
            'currency' => $this->pricing->currency(),
            'nights' => $query['nights'],
        ] + $extra);
    }

    public function stayDetails(array $entry): array
    {
        $query = $entry['context']['query'];
        $result = $this->send('POST', "/stays/search_results/{$entry['context']['search_result_id']}/actions/fetch_all_rates")->json('data');
        $accommodation = $result['accommodation'];

        $rooms = [];
        $entries = [];
        foreach ($accommodation['rooms'] ?? [] as $room) {
            foreach ($room['rates'] ?? [] as $rate) {
                $total = $this->pricing->sell((float) $rate['total_amount'], $rate['total_currency'], ItemType::Stay);
                $freeUntil = $this->freeCancellationUntil($rate);
                $id = OfferRef::make($this->key(), $rate['id']);
                $option = Shapes::room([
                    'id' => $id,
                    'name' => $room['name'].$this->board($rate['board_type'] ?? null),
                    'beds' => implode(', ', array_map(fn ($b) => "{$b['count']} {$b['type']} bed", $room['beds'] ?? [])),
                    'max_guests' => $query['adults'],
                    'price_per_night' => round($total / $query['nights'] / $query['rooms'], 3),
                    'total_amount' => $total,
                    'breakfast_included' => ! in_array($rate['board_type'] ?? 'room_only', ['room_only'], true),
                    'refundable' => $freeUntil !== null || (float) data_get($rate, 'cancellation_timeline.0.refund_amount', 0) > 0,
                    'free_cancellation_until' => $freeUntil,
                    'images' => array_column($room['photos'] ?? [], 'url'),
                    'quantity_available' => $rate['quantity_available'] ?? null,
                ]);
                $rooms[] = $option;
                $entries[$id] = ['offer' => $option, 'context' => ['rate_id' => $rate['id'], 'hotel' => $accommodation['name'], 'room' => $room['name'], 'query' => $query]];
            }
        }
        usort($rooms, fn ($a, $b) => $a['total_amount'] <=> $b['total_amount']);

        $hotel = $this->hotel($accommodation, $query, [
            'id' => $entry['offer']['id'],
            'rooms' => $rooms,
            'total_amount' => $rooms[0]['total_amount'] ?? $entry['offer']['total_amount'],
            'price_from' => $rooms[0]['price_per_night'] ?? $entry['offer']['price_from'],
            'free_cancellation' => (bool) array_filter(array_column($rooms, 'free_cancellation_until')),
        ]);

        return ['hotel' => $hotel, 'entries' => $entries];
    }

    private function board(?string $board): string
    {
        return match ($board) {
            'breakfast' => ' · '.__('Breakfast'),
            'half_board' => ' · '.__('Half board'),
            'full_board' => ' · '.__('Full board'),
            'all_inclusive' => ' · '.__('All inclusive'),
            default => '',
        };
    }

    private function freeCancellationUntil(array $rate): ?string
    {
        foreach ($rate['cancellation_timeline'] ?? [] as $step) {
            if ((float) $step['refund_amount'] >= (float) $rate['total_amount'] && strtotime($step['before']) > time()) {
                return $step['before'];
            }
        }

        return null;
    }

    public function quote(array $entry, array $input): Quote
    {
        if (empty($entry['context']['rate_id'])) {
            throw new ProviderException($this->key(), __('Pick a room first.'), status: 422);
        }

        $quote = $this->send('POST', '/stays/quotes', $this->body(['rate_id' => $entry['context']['rate_id']]))->json('data');
        $query = $entry['context']['query'];

        return new Quote(
            amount: (float) $quote['total_amount'],
            currency: $quote['total_currency'],
            title: $entry['context']['hotel'],
            summary: [
                'room' => $entry['offer'],
                'hotel' => $entry['context']['hotel'],
                'check_in' => $query['check_in'],
                'check_out' => $query['check_out'],
                'rooms' => $query['rooms'],
                'adults' => $query['adults'],
            ],
            data: [
                'rate_id' => $entry['context']['rate_id'],
                'quote_id' => $quote['id'],
                'cancellation_timeline' => data_get($quote, 'accommodation.rooms.0.rates.0.cancellation_timeline', []),
            ],
        );
    }

    public function book(OrderItem $item): BookingResult
    {
        $travellers = $item->travelers;
        $body = fn (string $quoteId) => $this->body([
            'quote_id' => $quoteId,
            'guests' => array_map(fn ($g) => ['given_name' => $g['given_name'], 'family_name' => $g['family_name']], $travellers['guests']),
            'email' => $travellers['email'],
            'phone_number' => $this->phone($travellers['phone_number']),
        ]);

        try {
            try {
                $booking = $this->send('POST', '/stays/bookings', $body($item->data('quote_id')))->json('data');
            } catch (ProviderException) {
                // Quotes expire; take a fresh one for the same rate once.
                $quote = $this->send('POST', '/stays/quotes', $this->body(['rate_id' => $item->data('rate_id')]))->json('data');
                $booking = $this->send('POST', '/stays/bookings', $body($quote['id']))->json('data');
            }

            return BookingResult::confirmed($booking['reference'] ?? $booking['id'], ['booking_id' => $booking['id']]);
        } catch (Throwable $e) {
            report($e);

            return BookingResult::failed($e->getMessage());
        }
    }

    public function cancel(OrderItem $item): CancellationResult
    {
        $bookingId = $item->data('booking_id');
        if (! $bookingId) {
            return new CancellationResult(false, message: __('Nothing to cancel.'));
        }

        $this->send('POST', "/stays/bookings/{$bookingId}/actions/cancel");

        // What comes back follows the rate's cancellation timeline.
        $refund = 0.0;
        foreach ($item->data('cancellation_timeline', []) as $step) {
            if (strtotime($step['before']) > time()) {
                $refund = (float) $step['refund_amount'];
                break;
            }
        }

        return new CancellationResult(true, $refund, $item->provider_currency);
    }
}
