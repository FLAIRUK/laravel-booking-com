<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Every request is authenticated with an API key (bearer token) and the
    | affiliate ID of an API user. Generate the key in the Affiliate Partner
    | Centre; Booking.com recommends replacing it at least once a year.
    |
    | @see https://developers.booking.com/demand/docs/development-guide/authentication
    |
    */

    'token' => env('BOOKING_COM_TOKEN'),

    'affiliate_id' => env('BOOKING_COM_AFFILIATE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Environment and version
    |--------------------------------------------------------------------------
    |
    | The sandbox uses the same credentials as production, with test
    | properties. The base URL is built from these unless you set one.
    |
    */

    'sandbox' => (bool) env('BOOKING_COM_SANDBOX', false),

    'version' => env('BOOKING_COM_API_VERSION', '3.2'),

    'base_url' => env('BOOKING_COM_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request defaults
    |--------------------------------------------------------------------------
    |
    | Merged into search, availability and order preview requests that don't
    | set them. The booker's country decides prices and how taxes are shown,
    | so pass the real visitor's country per request where you can.
    |
    */

    'defaults' => [
        'booker' => [
            'country' => env('BOOKING_COM_BOOKER_COUNTRY'),
            'platform' => env('BOOKING_COM_BOOKER_PLATFORM', 'desktop'),
        ],
        'currency' => env('BOOKING_COM_CURRENCY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('BOOKING_COM_TIMEOUT', 30),

    // Read requests are retried on connection errors and 5xx responses: [times, sleep milliseconds].
    // Order creation, modification and cancellation are never retried automatically.
    'retry' => [2, 500],

    /*
    |--------------------------------------------------------------------------
    | Reference data cache
    |--------------------------------------------------------------------------
    |
    | Languages, currencies, payment cards, chains and constants rarely change
    | and count against your rate limit, so they are cached. Set the TTL to
    | null to turn the cache off.
    |
    */

    'cache' => [
        'store' => env('BOOKING_COM_CACHE_STORE'),
        'ttl' => 60 * 60 * 24,
    ],

];
