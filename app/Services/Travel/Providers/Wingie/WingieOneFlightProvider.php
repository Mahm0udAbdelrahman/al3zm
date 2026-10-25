<?php

namespace App\Services\Travel\Providers\Wingie;

use App\Enums\ItemType;
use App\Models\OrderItem;
use App\Services\Travel\Contracts\FlightProvider;
use App\Services\Travel\Data\BookingResult;
use App\Services\Travel\Data\CancellationResult;
use App\Services\Travel\Data\FlightSearchQuery;
use App\Services\Travel\Data\Quote;
use App\Services\Travel\Providers\HttpProvider;
use App\Services\Travel\Support\OfferRef;
use App\Services\Travel\Support\Shapes;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Wingie One (Wingie Enuygun B2B) flights: search → price → book → ticket,
 * paid from the agency wallet.
 *
 * PROVISIONAL: the API reference (oneapi.wingieone.com) is behind a partner
 * login, so the paths live in config and the request/response fields below
 * are placeholders. Once the docs and sandbox keys are in, adjust
 * `searchBody()`, `mapOffer()`, `quote()` and `book()` — nothing outside this
 * class changes. Keep WINGIE_ENABLED off until then.
 */
class WingieOneFlightProvider extends HttpProvider implements FlightProvider
{
    public function key(): string
    {
        return 'wingie';
    }

    protected function configKey(): string
    {
        return 'wingie';
    }

    public function type(): ItemType
    {
        return ItemType::Flight;
    }

    public function enabled(): bool
    {
        return $this->config('flights') && filled($this->config('api_key'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->config('base_url'))
            ->withToken((string) $this->config('api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->config('timeout'));
    }

    private function path(string $name, ?string $id = null): string
    {
        return str_replace('{id}', (string) $id, $this->config("paths.{$name}"));
    }

    // TODO(wingie): match the search request to the API reference.
    private function searchBody(FlightSearchQuery $query): array
    {
        return [
            'legs' => array_map(fn ($leg) => [
                'origin' => $leg['from'],
                'destination' => $leg['to'],
                'departureDate' => $leg['date'],
            ], $query->legs),
            'passengers' => ['adult' => $query->adults, 'child' => $query->children, 'infant' => $query->infants],
            'cabinClass' => strtoupper($query->cabin),
            'currency' => $this->pricing->currency(),
        ];
    }

    public function searchFlights(FlightSearchQuery $query): PromiseInterface
    {
        return $this->sendAsync('POST', $this->path('search'), ['json' => $this->searchBody($query)])
            ->then(function (Response $response) use ($query) {
                $entries = [];
                foreach ($response->json('data.offers') ?? $response->json('offers') ?? [] as $offer) {
                    try {
                        $entries[] = [
                            'offer' => $this->mapOffer($offer, $query),
                            'context' => ['offer_id' => (string) $offer['id'], 'search_id' => $response->json('data.searchId'), 'query' => $query->toArray()],
                        ];
                    } catch (Throwable $e) {
                        report($e);
                    }
                }

                return $entries;
            });
    }

    // TODO(wingie): map the real offer fields.
    private function mapOffer(array $offer, FlightSearchQuery $query): array
    {
        $currency = data_get($offer, 'price.currency', $this->pricing->currency());
        $sell = fn ($amount) => $this->pricing->sell((float) $amount, $currency, ItemType::Flight);

        $legs = [];
        foreach ($offer['legs'] ?? [] as $i => $leg) {
            $flights = array_map(fn ($s) => [
                'id' => (string) ($s['id'] ?? $i),
                'carrier' => $s['marketingCarrier'] ?? $s['carrier'] ?? '',
                'number' => (string) ($s['flightNumber'] ?? ''),
                'airline' => Shapes::airline($s['marketingCarrier'] ?? $s['carrier'] ?? '', $s['carrierName'] ?? null),
                'operated_by' => $s['operatingCarrier'] ?? null,
                'from' => Shapes::airport($s['origin']),
                'to' => Shapes::airport($s['destination']),
                'depart_at' => $s['departureDateTime'],
                'arrive_at' => $s['arrivalDateTime'],
                'duration_minutes' => (int) ($s['durationMinutes'] ?? 0),
                'aircraft' => $s['aircraft'] ?? null,
            ], $leg['segments'] ?? []);

            $legs[] = Shapes::leg((string) ($leg['id'] ?? $i), $flights, $query->cabin, [
                'checked_bag_kg' => (int) data_get($leg, 'baggage.checkedKg', 0),
                'refundable' => (bool) ($offer['refundable'] ?? false),
            ]);
        }

        $total = $sell(data_get($offer, 'price.total', 0));
        $taxes = $sell(data_get($offer, 'price.tax', 0));

        return Shapes::itinerary(
            id: OfferRef::make($this->key(), (string) $offer['id']),
            provider: $this->key(),
            legs: $legs,
            base: $total - $taxes,
            taxes: $taxes,
            total: $total,
            currency: $this->pricing->currency(),
            adults: $query->adults,
            children: $query->children,
            extra: ['expires_at' => $offer['expiresAt'] ?? null, 'requires_instant_payment' => true],
        );
    }

    // TODO(wingie): price-check endpoint fields.
    public function quote(array $entry, array $input): Quote
    {
        $priced = $this->send('POST', $this->path('price'), ['json' => [
            'offerId' => $entry['context']['offer_id'],
            'searchId' => $entry['context']['search_id'],
        ]])->json('data') ?? [];

        $first = $entry['offer']['segments'][0];
        $last = end($entry['offer']['segments']);

        return new Quote(
            amount: (float) data_get($priced, 'price.total', $entry['offer']['total_amount']),
            currency: data_get($priced, 'price.currency', $this->pricing->currency()),
            title: "{$first['from']['code']} → {$last['to']['code']}",
            summary: ['itinerary' => $entry['offer'], 'passengers' => count($input['passengers'] ?? [])],
            data: [
                'offer_id' => $entry['context']['offer_id'],
                'price_token' => data_get($priced, 'priceToken'),
                'passengers' => $input['passengers'] ?? [],
            ],
        );
    }

    // TODO(wingie): booking + ticketing fields.
    public function book(OrderItem $item): BookingResult
    {
        try {
            $booking = $this->send('POST', $this->path('book'), ['json' => [
                'offerId' => $item->data('offer_id'),
                'priceToken' => $item->data('price_token'),
                'passengers' => array_map(fn ($p) => [
                    'type' => strtoupper($p['type'] ?? 'adult'),
                    'title' => $p['title'] ?? null,
                    'firstName' => $p['given_name'],
                    'lastName' => $p['family_name'],
                    'gender' => $p['gender'],
                    'birthDate' => $p['born_on'],
                    'passportNumber' => $p['passport_number'] ?? null,
                    'passportCountry' => $p['passport_country'] ?? null,
                    'passportExpiry' => $p['passport_expires_on'] ?? null,
                ], $item->data('passengers', [])),
                'contact' => ['email' => $item->order->contact_email, 'phone' => $item->order->contact_phone],
                'clientReference' => $item->order->reference,
            ]])->json('data');

            $bookingId = (string) ($booking['bookingId'] ?? $booking['id']);
            $ticket = $this->send('POST', $this->path('ticket', $bookingId))->json('data');

            return BookingResult::confirmed($booking['pnr'] ?? $ticket['pnr'] ?? $bookingId, ['booking_id' => $bookingId, 'tickets' => $ticket['tickets'] ?? []]);
        } catch (Throwable $e) {
            report($e);

            return BookingResult::failed($e->getMessage());
        }
    }

    public function cancel(OrderItem $item): CancellationResult
    {
        $result = $this->send('POST', $this->path('cancel', $item->data('booking_id')))->json('data') ?? [];

        return new CancellationResult(true, isset($result['refundAmount']) ? (float) $result['refundAmount'] : null, $result['currency'] ?? $item->provider_currency, $result);
    }
}
