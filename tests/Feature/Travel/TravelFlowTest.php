<?php

namespace Tests\Feature\Travel;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TravelFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'travel.providers.duffel.token' => 'duffel_test',
            'travel.providers.wingie.flights' => true,
            'travel.providers.wingie.api_key' => 'wingie_test',
            'travel.providers.booking.stays' => true,
            'travel.providers.booking.token' => 'booking_test',
            'travel.providers.booking.affiliate_id' => '123',
            'payments.gateways.fake.enabled' => true,
        ]);
        Http::preventStrayRequests();
    }

    private function segment(string $carrier, string $number, string $depart, string $arrive): array
    {
        return [
            'id' => "seg_{$carrier}{$number}",
            'origin' => ['iata_code' => 'MCT', 'name' => 'Muscat International', 'city_name' => 'Muscat', 'iata_country_code' => 'OM'],
            'destination' => ['iata_code' => 'DXB', 'name' => 'Dubai International', 'city_name' => 'Dubai', 'iata_country_code' => 'AE'],
            'departing_at' => $depart,
            'arriving_at' => $arrive,
            'duration' => 'PT1H15M',
            'marketing_carrier' => ['iata_code' => $carrier, 'name' => "Airline {$carrier}"],
            'marketing_carrier_flight_number' => $number,
            'passengers' => [['passenger_id' => 'pas_1', 'cabin_class' => 'economy', 'baggages' => [['type' => 'checked', 'quantity' => 1]]]],
        ];
    }

    private function duffelOffer(string $id, string $amount, string $carrier, string $number, bool $holdable = true): array
    {
        return [
            'id' => $id,
            'total_amount' => $amount,
            'total_currency' => 'USD',
            'base_amount' => (string) ($amount - 20),
            'tax_amount' => '20.00',
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'owner' => ['iata_code' => $carrier, 'name' => "Airline {$carrier}"],
            'passengers' => [['id' => 'pas_1', 'type' => 'adult']],
            'conditions' => ['refund_before_departure' => ['allowed' => true]],
            'payment_requirements' => ['requires_instant_payment' => ! $holdable],
            'available_services' => [],
            'slices' => [[
                'id' => "sli_{$id}",
                'duration' => 'PT1H15M',
                'segments' => [$this->segment($carrier, $number, '2026-12-01T08:00:00', '2026-12-01T09:15:00')],
            ]],
        ];
    }

    private function wingieSearch(): array
    {
        return ['data' => ['searchId' => 'ws_1', 'offers' => [[
            'id' => 'w_1',
            'price' => ['total' => 90, 'tax' => 15, 'currency' => 'USD'],
            'legs' => [['segments' => [[
                'marketingCarrier' => 'WY', 'flightNumber' => '601', 'origin' => 'MCT', 'destination' => 'DXB',
                'departureDateTime' => '2026-12-01T08:00:00', 'arrivalDateTime' => '2026-12-01T09:15:00', 'durationMinutes' => 75,
            ]]]],
        ]]]];
    }

    private function searchFlights(): array
    {
        return $this->postJson('/api/v1/flights/search', [
            'trip_type' => 'oneWay',
            'legs' => [['from' => 'MCT', 'to' => 'DXB', 'date' => '2026-12-01']],
            'adults' => 1,
            'cabin' => 'economy',
        ])->assertOk()->json('data');
    }

    private function passenger(): array
    {
        return [
            'type' => 'adult', 'gender' => 'm', 'given_name' => 'Ali', 'family_name' => 'Mazen',
            'born_on' => '1990-01-01', 'email' => 'ali@example.com', 'phone_number' => '+96890000000',
        ];
    }

    public function test_flight_search_merges_providers_and_keeps_the_cheaper_duplicate(): void
    {
        Http::fake([
            'api.duffel.com/air/offer_requests*' => Http::response(['data' => ['offers' => [
                $this->duffelOffer('off_wy', '100.00', 'WY', '601'),
                $this->duffelOffer('off_ek', '150.00', 'EK', '863'),
            ]]]),
            'oneapi.wingieone.com/*' => Http::response($this->wingieSearch()),
        ]);

        $data = $this->searchFlights();

        $this->assertSame('ok', $data['providers']['duffel']['status']);
        $this->assertSame('ok', $data['providers']['wingie']['status']);
        // WY601 came from both: Wingie's 90 USD beats Duffel's 100 USD.
        $this->assertCount(2, $data['itineraries']);
        $first = $data['itineraries'][0];
        $this->assertSame('wingie', $first['provider']);
        $this->assertSame('duffel', $first['also_offered_by'][0]['provider']);
        $this->assertEqualsWithDelta(90 * 0.3845, $first['total_amount'], 0.001);
        $this->assertSame('OMR', $first['currency']);
        $this->assertSame('WY 601', $first['segments'][0]['flight_number']);
        $this->assertSame('EK', $data['itineraries'][1]['segments'][0]['airline']['code']);

        // Re-sorting the stored search does not call the providers again.
        Http::fake(fn () => throw new \RuntimeException('no provider call expected'));
        $resorted = $this->getJson("/api/v1/flights/search/{$data['search_id']}?sort=price&direction=desc")->assertOk()->json('data');
        $this->assertSame('EK', $resorted['itineraries'][0]['segments'][0]['airline']['code']);
    }

    public function test_one_provider_failing_does_not_fail_the_search(): void
    {
        Http::fake([
            'api.duffel.com/air/offer_requests*' => Http::response(['data' => ['offers' => [$this->duffelOffer('off_wy', '100.00', 'WY', '601')]]]),
            'oneapi.wingieone.com/*' => Http::response(['message' => 'down'], 500),
        ]);

        $data = $this->searchFlights();

        $this->assertSame('error', $data['providers']['wingie']['status']);
        $this->assertCount(1, $data['itineraries']);
        $this->assertSame('duffel', $data['itineraries'][0]['provider']);
    }

    public function test_stay_search_merges_duffel_and_booking_com(): void
    {
        $hotel = ['name' => 'Grand Hyatt Muscat', 'lat' => 23.6020, 'lng' => 58.4500];
        Http::fake([
            'api.duffel.com/stays/search' => Http::response(['data' => ['results' => [[
                'id' => 'srr_1',
                'cheapest_rate_total_amount' => '300.00',
                'cheapest_rate_currency' => 'USD',
                'accommodation' => [
                    'name' => $hotel['name'], 'rating' => 5, 'review_score' => 8.9,
                    'location' => ['geographic_coordinates' => ['latitude' => $hotel['lat'], 'longitude' => $hotel['lng']], 'address' => ['city_name' => 'Muscat']],
                    'amenities' => [['type' => 'pool', 'description' => 'Pool']],
                ],
            ]]]]),
            'demandapi.booking.com/3.1/accommodations/search' => Http::response(['data' => [[
                'id' => 777, 'currency' => ['booker' => 'OMR'], 'price' => ['total' => 100],
                'products' => [['id' => '777_1', 'room' => 1, 'price' => ['total' => 100], 'policies' => ['cancellation' => ['type' => 'free_cancellation']]]],
            ]]]),
            'demandapi.booking.com/3.1/accommodations/details' => Http::response(['data' => [[
                'id' => 777, 'name' => ['en-gb' => $hotel['name']], 'rating' => ['stars' => 5, 'review_score' => 9.0],
                'location' => ['coordinates' => ['latitude' => $hotel['lat'], 'longitude' => $hotel['lng']]],
                'rooms' => [['id' => 1, 'name' => ['en-gb' => 'Deluxe King']]],
            ]]]),
        ]);

        $response = $this->getJson('/api/v1/stays/search?city=MCT&check_in=2026-12-01&check_out=2026-12-03&adults=2&rooms=1')->assertOk();

        $hotels = $response->json('data');
        $this->assertCount(1, $hotels);
        // Booking.com: 100 OMR beats Duffel: 300 USD = 115.35 OMR.
        $this->assertSame('booking', $hotels[0]['provider']);
        $this->assertSame(50, (int) $hotels[0]['price_from']);
        $this->assertTrue($hotels[0]['free_cancellation']);
        $this->assertSame('duffel', $hotels[0]['also_offered_by'][0]['provider']);
        $this->assertSame(2, $response->json('meta.nights'));

        $details = $this->getJson("/api/v1/stays/{$hotels[0]['id']}")->assertOk()->json('data');
        $this->assertSame('Deluxe King', $details['rooms'][0]['name']);
    }

    public function test_order_is_held_paid_and_booked(): void
    {
        $orders = 0;
        Http::fake([
            'api.duffel.com/air/offer_requests*' => Http::response(['data' => ['offers' => [$this->duffelOffer('off_wy', '100.00', 'WY', '601')]]]),
            'oneapi.wingieone.com/*' => Http::response(['data' => ['offers' => []]]),
            'api.duffel.com/air/offers/off_wy*' => Http::response(['data' => $this->duffelOffer('off_wy', '100.00', 'WY', '601')]),
            'api.duffel.com/air/orders/ord_1' => Http::response(['data' => ['id' => 'ord_1', 'booking_reference' => 'ABC123', 'total_amount' => '100.00', 'total_currency' => 'USD']]),
            'api.duffel.com/air/orders' => function () use (&$orders) {
                $orders++;

                return Http::response(['data' => ['id' => 'ord_1', 'booking_reference' => 'ABC123', 'payment_status' => ['payment_required_by' => now()->addHour()->toIso8601String()]]]);
            },
            'api.duffel.com/air/payments' => Http::response(['data' => ['id' => 'pay_1']]),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $offerId = $this->searchFlights()['itineraries'][0]['id'];

        $order = $this->postJson('/api/v1/orders', [
            'items' => [['type' => 'flight', 'offer_id' => $offerId, 'passengers' => [$this->passenger()]]],
        ])->assertCreated()->json('data');

        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame('held', $order['items'][0]['status']);
        $this->assertEqualsWithDelta(38.45, $order['total_amount'], 0.001);

        $payment = $this->postJson("/api/v1/orders/{$order['reference']}/pay", ['gateway' => 'fake'])->assertCreated()->json('data');
        $this->assertStringContainsString('simulate=paid', $payment['redirect_url']);

        // The gateway sends the user back; the queue is sync in tests.
        $this->get($payment['redirect_url'])->assertRedirectContains('status=paid');

        $done = $this->getJson("/api/v1/orders/{$order['reference']}")->assertOk()->json('data');
        $this->assertSame('confirmed', $done['status']);
        $this->assertSame('confirmed', $done['items'][0]['status']);
        $this->assertSame('ABC123', $done['items'][0]['reference']);
        $this->assertSame(1, $orders, 'held once, paid by /air/payments — never ordered twice');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.duffel.com/air/payments' && $r['data']['order_id'] === 'ord_1');
    }

    public function test_failed_booking_is_refunded(): void
    {
        Http::fake([
            'api.duffel.com/air/offer_requests*' => Http::response(['data' => ['offers' => [$this->duffelOffer('off_wy', '100.00', 'WY', '601', holdable: false)]]]),
            'oneapi.wingieone.com/*' => Http::response(['data' => ['offers' => []]]),
            'api.duffel.com/air/offers/off_wy*' => Http::response(['data' => $this->duffelOffer('off_wy', '100.00', 'WY', '601', holdable: false)]),
            'api.duffel.com/air/orders' => Http::response(['errors' => [['message' => 'Offer no longer available']]], 422),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $offerId = $this->searchFlights()['itineraries'][0]['id'];
        $order = $this->postJson('/api/v1/orders', [
            'items' => [['type' => 'flight', 'offer_id' => $offerId, 'passengers' => [$this->passenger()]]],
        ])->assertCreated()->json('data');
        $this->assertSame('quoted', $order['items'][0]['status']);

        $payment = $this->postJson("/api/v1/orders/{$order['reference']}/pay", ['gateway' => 'fake'])->json('data');
        $this->get($payment['redirect_url']);

        $done = Order::where('reference', $order['reference'])->with('items', 'payments')->first();
        $this->assertSame('failed', $done->status->value);
        $this->assertStringContainsString('Offer no longer available', $done->items[0]->error);
        $this->assertSame('refunded', $done->payments[0]->status->value);
    }

    public function test_price_change_needs_confirmation(): void
    {
        Http::fake([
            'api.duffel.com/air/offer_requests*' => Http::response(['data' => ['offers' => [$this->duffelOffer('off_wy', '100.00', 'WY', '601')]]]),
            'oneapi.wingieone.com/*' => Http::response(['data' => ['offers' => []]]),
            'api.duffel.com/air/offers/off_wy*' => Http::response(['data' => $this->duffelOffer('off_wy', '120.00', 'WY', '601')]),
            'api.duffel.com/air/orders' => Http::response(['data' => ['id' => 'ord_1', 'booking_reference' => 'ABC123']]),
        ]);
        Sanctum::actingAs(User::factory()->create());

        $offerId = $this->searchFlights()['itineraries'][0]['id'];
        $body = ['items' => [['type' => 'flight', 'offer_id' => $offerId, 'passengers' => [$this->passenger()]]]];

        $this->postJson('/api/v1/orders', $body)
            ->assertStatus(409)
            ->assertJsonPath('data.items.0.new_amount', 46.14);

        $this->postJson('/api/v1/orders', $body + ['accept_price_change' => true])->assertCreated();
    }

    public function test_gateways_are_listed(): void
    {
        $this->getJson('/api/v1/payments/gateways')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'fake');
    }
}
