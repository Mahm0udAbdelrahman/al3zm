# Travel integration

Flights, stays and activities from several providers behind one API, one order and
one payment. Everything lives under `/api/v1` — the paths in the mobile app's
`Endpoints`.

## Providers

| Vertical | Provider | Class | Status |
|---|---|---|---|
| Flights | Duffel | `Services/Travel/Providers/Duffel/DuffelFlightProvider` | Live — set `DUFFEL_ACCESS_TOKEN` |
| Flights | Wingie One | `Services/Travel/Providers/Wingie/WingieOneFlightProvider` | **Provisional** payloads — fill in from the API docs, then `WINGIE_ENABLED=true` |
| Stays | Duffel Stays | `…/Duffel/DuffelStayProvider` | Live — needs Stays enabled on the Duffel account |
| Stays | Booking.com Demand API | `…/BookingCom/BookingComStayProvider` | Needs affiliate approval for booking through the API |
| Activities | GetYourGuide Partner API | `…/GetYourGuide/GetYourGuideActivityProvider` | Needs partner access |

A provider is used only when it is enabled **and** its keys are set
(`GET /api/v1/travel/providers` shows which are on). Adding one = a class
implementing `FlightProvider` / `StayProvider` / `ActivityProvider` + a line in
`ProviderRegistry::PROVIDERS`.

## Search: every provider at once

`SearchAggregator` sends the search to every enabled provider of the vertical in
parallel (one shared curl handler), then:

1. maps each answer to the app's shapes (`Support/Shapes`), prices converted to
   `TRAVEL_CURRENCY` and marked up (`TRAVEL_MARKUP_*`);
2. merges them and drops duplicates — the same flight (carrier + number + time)
   or hotel (name + ~100 m) from two providers keeps the cheaper, the other is
   listed in `also_offered_by`;
3. caches the result: `GET …/search/{search_id}` re-sorts, filters and pages
   without calling the providers again.

A provider that fails or times out is reported in `providers` — the search still
returns the others.

Every id the app gets (`offer_id`, hotel id, room id, activity slot id) is
`{provider}-{base64 provider id}`, so ordering it goes back to the right provider.

## Endpoints

| | |
|---|---|
| `GET /airports?q=` | favourites, or live suggestions |
| `GET /flights/search-defaults?to=` | |
| `POST /flights/search` | `{trip_type, legs[{from,to,date}], return_date?, adults, children, infants, cabin}` + sort/filter |
| `GET /flights/search/{id}` | `sort=price\|duration\|departure\|arrival\|best`, `max_stops`, `airlines[]`, `refundable`, `max_price`, `providers[]`, `page`, `per_page` |
| `GET /flights/offers/{offer}` | fresh price + extra bags (`services`) |
| `GET /flights/offers/{offer}/seats` | seat map; each free seat has a service id |
| `GET /stays/cities?q=`, `/stays/search-defaults` | |
| `GET /stays/search` | `city` (or `latitude`+`longitude`), `check_in`, `check_out`, `adults`, `rooms`, `children_ages[]`; `data` = hotels, `meta` = search |
| `GET /stays/search/{id}` | `sort=price\|score\|stars\|distance\|best`, `min_stars`, `min_score`, `free_cancellation` |
| `GET /stays/{hotel}` | every room/rate — **room ids are what you order** |
| `GET /activities/cities`, `GET /activities?city=&date=` | |
| `GET /activities/{id}` | details + options |
| `GET /activities/{id}/availability?from=&to=` | slots — **slot ids are what you order** |
| `GET /payments/gateways?country=` | gateways to show the user |
| 🔒 `POST /orders` | create an order (below) |
| 🔒 `POST /orders/{ref}/pay` | `{gateway}` → `redirect_url` to open |
| 🔒 `GET /orders`, `GET /orders/{ref}`, `POST /orders/{ref}/cancel` | |
| 🔒 `GET /payments/{id}` | poll after the checkout closes |
| `GET /payments/{id}/return` | gateway → back to `PAYMENTS_APP_RETURN_URL?payment=&order=&status=` |
| `POST /payments/webhooks/{gateway}` | gateway webhooks |

## Ordering anything

```json
POST /api/v1/orders
{
  "items": [
    {"type": "flight", "offer_id": "duffel-…",
     "passengers": [{"type": "adult", "gender": "m", "given_name": "Ali", "family_name": "Mazen",
                     "born_on": "1990-01-01", "email": "…", "phone_number": "+968…"}],
     "services": [{"id": "ase_…", "quantity": 1}]},
    {"type": "stay", "offer_id": "<room id from GET /stays/{hotel}>",
     "guests": [{"given_name": "Ali", "family_name": "Mazen"}], "email": "…", "phone_number": "+968…"},
    {"type": "activity", "offer_id": "<slot id from availability>",
     "participants": [{"category_id": "1", "quantity": 2}],
     "traveler": {"given_name": "Ali", "family_name": "Mazen", "email": "…", "phone_number": "+968…"}}
  ],
  "contact": {"email": "…", "phone": "…"}
}
```

1. Every item is priced again with its provider. If a price moved more than
   `TRAVEL_PRICE_TOLERANCE` % → **409** with old/new prices; resend with
   `"accept_price_change": true`.
2. Items are **held** where the provider allows (Duffel hold orders, GetYourGuide
   reservations) for `TRAVEL_ORDER_TTL` minutes. `travel:expire-orders` (scheduled
   every minute) releases unpaid ones.
3. `POST /orders/{ref}/pay {gateway}` → open `redirect_url`.
4. The return redirect / webhook only triggers a status check **with the
   gateway**; a paid order is queued to `FulfillOrder`.
5. Each item is booked (held ones are paid from the Duffel balance). Items that
   fail are **refunded automatically**; the order ends `confirmed`,
   `partially_confirmed` or `failed`. A refund the gateway refuses is kept in
   `refund_pending` for manual handling.

Order statuses: `pending_payment → paid → fulfilling → confirmed | partially_confirmed | failed`,
or `cancelled` / `expired`.

## Payments

| Gateway | Where | Currencies |
|---|---|---|
| Thawani Pay | Oman | OMR (full refunds only) |
| Tap Payments | GCC + international, Apple Pay, KNET, mada, Benefit | OMR, AED, SAR, KWD, BHD, QAR, EGP, USD, EUR, GBP |
| Stripe Checkout | International cards, Apple/Google Pay | `STRIPE_CURRENCIES` |
| Fake | Local/testing only, never in production | any |

An order in a currency the gateway can't charge is converted to the gateway's
first currency. Webhook URLs to register with each gateway:
`{APP_URL}/api/v1/payments/webhooks/{thawani|tap|stripe}`.

## Money flow

You are the merchant: the user pays you, you pay the providers — keep the
**Duffel balance**, **Wingie wallet** topped up; Booking.com is paid with the card
in `BOOKING_COM_CARD_*` (or `BOOKING_COM_PAYMENT_TIMING=pay_at_the_property`);
GetYourGuide invoices you (`GETYOURGUIDE_PAYMENT_TYPE`).

## Running locally

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve
php artisan queue:work     # bookings run on the queue
php artisan schedule:work  # releases unpaid holds
php artisan test --filter=Travel
```

## Before going live

- Wingie One: replace the `TODO(wingie)` payloads in `WingieOneFlightProvider`.
- Booking.com / GetYourGuide: verify field names against their sandboxes.
- Set real exchange rates (`TRAVEL_RATES_URL`) — the static table is a fallback.
- Mobile: the app needs a traveller-details step and a checkout step (order → pay
  → open `redirect_url` → handle the deep link) instead of booking directly.
