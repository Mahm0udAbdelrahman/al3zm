<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateways
    |--------------------------------------------------------------------------
    |
    | The app lists the enabled gateways (GET /api/v1/payments/gateways) and the
    | user picks one. `countries` limits a gateway to cards/users from those
    | countries (empty = everywhere); `currencies` are what it can charge — an
    | order in another currency is converted to the gateway's first currency.
    |
    */

    // Where the user lands after paying: the app's deep link. The payment id
    // and status are appended as query parameters.
    'app_return_url' => env('PAYMENTS_APP_RETURN_URL', 'mnaden://payment-result'),

    'gateways' => [

        // Oman — cards and local debit, OMR only.
        'thawani' => [
            'enabled' => (bool) env('THAWANI_ENABLED', false),
            'name' => ['en' => 'Thawani Pay', 'ar' => 'ثواني'],
            'base_url' => env('THAWANI_BASE_URL', 'https://uatcheckout.thawani.om/api/v1'),
            'checkout_url' => env('THAWANI_CHECKOUT_URL', 'https://uatcheckout.thawani.om/pay'),
            'secret_key' => env('THAWANI_SECRET_KEY'),
            'publishable_key' => env('THAWANI_PUBLISHABLE_KEY'),
            'webhook_secret' => env('THAWANI_WEBHOOK_SECRET'),
            'currencies' => ['OMR'],
            'countries' => [],
        ],

        // GCC (Oman included) and international cards, Apple Pay, KNET, mada…
        'tap' => [
            'enabled' => (bool) env('TAP_ENABLED', false),
            'name' => ['en' => 'Tap Payments', 'ar' => 'تاب'],
            'base_url' => env('TAP_BASE_URL', 'https://api.tap.company/v2'),
            'secret_key' => env('TAP_SECRET_KEY'),
            'source' => env('TAP_SOURCE', 'src_all'),
            'currencies' => ['OMR', 'AED', 'SAR', 'KWD', 'BHD', 'QAR', 'EGP', 'USD', 'EUR', 'GBP'],
            'countries' => [],
        ],

        // International cards, Apple Pay / Google Pay.
        'stripe' => [
            'enabled' => (bool) env('STRIPE_ENABLED', false),
            'name' => ['en' => 'Card (Stripe)', 'ar' => 'بطاقة (Stripe)'],
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com/v1'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currencies' => array_filter(explode(',', env('STRIPE_CURRENCIES', 'USD,EUR,GBP,AED,SAR,OMR'))),
            'countries' => [],
        ],

        // Local development and tests only: pays instantly, never in production.
        'fake' => [
            'enabled' => (bool) env('FAKE_GATEWAY_ENABLED', env('APP_ENV') !== 'production'),
            'name' => ['en' => 'Test payment', 'ar' => 'دفع تجريبي'],
            'currencies' => [],
            'countries' => [],
        ],
    ],
];
