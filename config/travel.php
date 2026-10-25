<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display currency & pricing
    |--------------------------------------------------------------------------
    |
    | Every provider price is converted to this currency and marked up by the
    | percentage for its vertical before it reaches the app. The order total
    | the user pays is always in this currency.
    |
    */

    'currency' => env('TRAVEL_CURRENCY', 'OMR'),

    'markup' => [
        'flight' => (float) env('TRAVEL_MARKUP_FLIGHT', 0),
        'stay' => (float) env('TRAVEL_MARKUP_STAY', 0),
        'activity' => (float) env('TRAVEL_MARKUP_ACTIVITY', 0),
    ],

    // Units of the display currency per one unit of each currency. Used when
    // `rates_url` is empty or unreachable. OMR is pegged at 0.3845 per USD.
    'rates' => [
        'OMR' => 1,
        'USD' => 0.3845,
        'EUR' => 0.4150,
        'GBP' => 0.4900,
        'AED' => 0.1047,
        'SAR' => 0.1025,
        'QAR' => 0.1056,
        'KWD' => 1.2500,
        'BHD' => 1.0227,
        'EGP' => 0.0079,
        'TRY' => 0.0093,
        'JOD' => 0.5423,
        'INR' => 0.0045,
    ],

    // Optional live rates, e.g. https://open.er-api.com/v6/latest/OMR — any
    // endpoint answering {"rates": {"USD": 2.6, …}} based on the display currency.
    'rates_url' => env('TRAVEL_RATES_URL'),
    'rates_ttl' => 6 * 3600,

    /*
    |--------------------------------------------------------------------------
    | Search & booking
    |--------------------------------------------------------------------------
    */

    // How long a search and its offers stay usable (providers expire offers
    // after ~20–30 minutes, so keep this at or below that).
    'search_ttl' => (int) env('TRAVEL_SEARCH_TTL', 1500),

    // A re-quoted price may move by this many percent before the user has to
    // accept the new price (`accept_price_change`).
    'price_change_tolerance' => (float) env('TRAVEL_PRICE_TOLERANCE', 1),

    // Hold the seat / activity while the user pays, where the provider allows.
    'hold_when_possible' => (bool) env('TRAVEL_HOLD_WHEN_POSSIBLE', true),

    // An unpaid order is released after this many minutes.
    'order_ttl' => (int) env('TRAVEL_ORDER_TTL', 20),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Each provider is off until its keys are set. A search asks every enabled
    | provider of that vertical at once and merges the answers.
    |
    */

    'providers' => [

        'duffel' => [
            'flights' => (bool) env('DUFFEL_FLIGHTS_ENABLED', true),
            'stays' => (bool) env('DUFFEL_STAYS_ENABLED', true),
            'base_url' => env('DUFFEL_BASE_URL', 'https://api.duffel.com'),
            'token' => env('DUFFEL_ACCESS_TOKEN'),
            'version' => env('DUFFEL_VERSION', 'v2'),
            'timeout' => (int) env('DUFFEL_TIMEOUT', 30),
            'supplier_timeout' => (int) env('DUFFEL_SUPPLIER_TIMEOUT', 20000),
            'stays_radius_km' => (int) env('DUFFEL_STAYS_RADIUS', 10),
        ],

        // Wingie One B2B flights. The payloads in WingieOneFlightProvider are
        // provisional until the API docs are in — keep this off until then.
        'wingie' => [
            'flights' => (bool) env('WINGIE_ENABLED', false),
            'base_url' => env('WINGIE_BASE_URL', 'https://oneapi.wingieone.com'),
            'api_key' => env('WINGIE_API_KEY'),
            'timeout' => (int) env('WINGIE_TIMEOUT', 30),
            'paths' => [
                'search' => env('WINGIE_PATH_SEARCH', '/api/v1/flights/search'),
                'price' => env('WINGIE_PATH_PRICE', '/api/v1/flights/price'),
                'book' => env('WINGIE_PATH_BOOK', '/api/v1/bookings'),
                'ticket' => env('WINGIE_PATH_TICKET', '/api/v1/bookings/{id}/ticket'),
                'cancel' => env('WINGIE_PATH_CANCEL', '/api/v1/bookings/{id}/cancel'),
            ],
        ],

        // Booking.com Demand API (affiliate partners). Sandbox:
        // https://demandapi-sandbox.booking.com/3.1
        'booking' => [
            'stays' => (bool) env('BOOKING_COM_ENABLED', false),
            'base_url' => env('BOOKING_COM_BASE_URL', 'https://demandapi.booking.com/3.1'),
            'token' => env('BOOKING_COM_TOKEN'),
            'affiliate_id' => env('BOOKING_COM_AFFILIATE_ID'),
            'timeout' => (int) env('BOOKING_COM_TIMEOUT', 30),
            'booker_country' => env('BOOKING_COM_BOOKER_COUNTRY', 'om'),
            'language' => env('BOOKING_COM_LANGUAGE', 'en-gb'),
            'radius_km' => (int) env('BOOKING_COM_RADIUS', 10),
            // How Booking.com is paid once the user has paid us.
            'payment' => [
                'timing' => env('BOOKING_COM_PAYMENT_TIMING', 'pay_online_now'),
                'method' => env('BOOKING_COM_PAYMENT_METHOD', 'card'),
                'card' => [
                    'cardholder' => env('BOOKING_COM_CARD_HOLDER'),
                    'number' => env('BOOKING_COM_CARD_NUMBER'),
                    'cvc' => env('BOOKING_COM_CARD_CVC'),
                    'expiry_date' => env('BOOKING_COM_CARD_EXPIRY'),
                ],
            ],
        ],

        // GetYourGuide Partner API. Testing: https://api.testing.getyourguide.com/1
        'getyourguide' => [
            'activities' => (bool) env('GETYOURGUIDE_ENABLED', false),
            'base_url' => env('GETYOURGUIDE_BASE_URL', 'https://api.getyourguide.com/1'),
            'token' => env('GETYOURGUIDE_ACCESS_TOKEN'),
            'timeout' => (int) env('GETYOURGUIDE_TIMEOUT', 30),
            'currency' => env('GETYOURGUIDE_CURRENCY', 'USD'),
            'image_format' => env('GETYOURGUIDE_IMAGE_FORMAT', '21'),
            'radius_km' => (int) env('GETYOURGUIDE_RADIUS', 30),
            'payment_type' => env('GETYOURGUIDE_PAYMENT_TYPE', 'invoice'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    |
    | The cities shown before the user types (stays, activities) and the
    | airports offered by default. Anything else is looked up live.
    |
    */

    'cities' => [
        ['id' => 'MCT', 'name' => ['en' => 'Muscat', 'ar' => 'مسقط'], 'country' => 'OM', 'latitude' => 23.5880, 'longitude' => 58.3829],
        ['id' => 'SLL', 'name' => ['en' => 'Salalah', 'ar' => 'صلالة'], 'country' => 'OM', 'latitude' => 17.0151, 'longitude' => 54.0924],
        ['id' => 'DXB', 'name' => ['en' => 'Dubai', 'ar' => 'دبي'], 'country' => 'AE', 'latitude' => 25.2048, 'longitude' => 55.2708],
        ['id' => 'DOH', 'name' => ['en' => 'Doha', 'ar' => 'الدوحة'], 'country' => 'QA', 'latitude' => 25.2854, 'longitude' => 51.5310],
        ['id' => 'RUH', 'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'], 'country' => 'SA', 'latitude' => 24.7136, 'longitude' => 46.6753],
        ['id' => 'CAI', 'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'], 'country' => 'EG', 'latitude' => 30.0444, 'longitude' => 31.2357],
        ['id' => 'IST', 'name' => ['en' => 'Istanbul', 'ar' => 'إسطنبول'], 'country' => 'TR', 'latitude' => 41.0082, 'longitude' => 28.9784],
        ['id' => 'LON', 'name' => ['en' => 'London', 'ar' => 'لندن'], 'country' => 'GB', 'latitude' => 51.5072, 'longitude' => -0.1276],
        ['id' => 'PAR', 'name' => ['en' => 'Paris', 'ar' => 'باريس'], 'country' => 'FR', 'latitude' => 48.8566, 'longitude' => 2.3522],
        ['id' => 'KUL', 'name' => ['en' => 'Kuala Lumpur', 'ar' => 'كوالالمبور'], 'country' => 'MY', 'latitude' => 3.1390, 'longitude' => 101.6869],
    ],

    'airports' => [
        ['code' => 'MCT', 'city' => ['en' => 'Muscat', 'ar' => 'مسقط'], 'name' => ['en' => 'Muscat International Airport', 'ar' => 'مطار مسقط الدولي'], 'country' => 'OM', 'latitude' => 23.5933, 'longitude' => 58.2844],
        ['code' => 'SLL', 'city' => ['en' => 'Salalah', 'ar' => 'صلالة'], 'name' => ['en' => 'Salalah International Airport', 'ar' => 'مطار صلالة الدولي'], 'country' => 'OM', 'latitude' => 17.0387, 'longitude' => 54.0913],
        ['code' => 'DXB', 'city' => ['en' => 'Dubai', 'ar' => 'دبي'], 'name' => ['en' => 'Dubai International Airport', 'ar' => 'مطار دبي الدولي'], 'country' => 'AE', 'latitude' => 25.2532, 'longitude' => 55.3657],
        ['code' => 'DOH', 'city' => ['en' => 'Doha', 'ar' => 'الدوحة'], 'name' => ['en' => 'Hamad International Airport', 'ar' => 'مطار حمد الدولي'], 'country' => 'QA', 'latitude' => 25.2731, 'longitude' => 51.6081],
        ['code' => 'RUH', 'city' => ['en' => 'Riyadh', 'ar' => 'الرياض'], 'name' => ['en' => 'King Khalid International Airport', 'ar' => 'مطار الملك خالد الدولي'], 'country' => 'SA', 'latitude' => 24.9576, 'longitude' => 46.6988],
        ['code' => 'CAI', 'city' => ['en' => 'Cairo', 'ar' => 'القاهرة'], 'name' => ['en' => 'Cairo International Airport', 'ar' => 'مطار القاهرة الدولي'], 'country' => 'EG', 'latitude' => 30.1219, 'longitude' => 31.4056],
        ['code' => 'IST', 'city' => ['en' => 'Istanbul', 'ar' => 'إسطنبول'], 'name' => ['en' => 'Istanbul Airport', 'ar' => 'مطار إسطنبول'], 'country' => 'TR', 'latitude' => 41.2753, 'longitude' => 28.7519],
        ['code' => 'LHR', 'city' => ['en' => 'London', 'ar' => 'لندن'], 'name' => ['en' => 'Heathrow Airport', 'ar' => 'مطار هيثرو'], 'country' => 'GB', 'latitude' => 51.4700, 'longitude' => -0.4543],
    ],
];
