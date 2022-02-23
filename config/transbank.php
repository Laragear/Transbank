<?php

use Laragear\Transbank\Services\Webpay;
use Laragear\Transbank\Transbank;

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | By default, the environment in your application will be 'integration'.
    | When you're ready to accept real payments using Transbank services,
    | change the environment to 'production' to use your credentials.
    |
    | Supported: 'integration', 'production'
    |
    */

    'environment' => env('TRANSBANK_ENV'),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | On busy days, Transbank servers may take a while to respond or fail the
    | request of your application. Here are some defaults to treat timeouts
    | and retries when any Transbank request encounter any given problem.
    |
    | The "options" key is passed down to the underlying Guzzle Client.
    |
    | @see https://docs.guzzlephp.org/en/stable/request-options.html
    |
    */

    'http' => [
        'timeout' => 10,
        'retries' => 3,
        'options' => [
            'synchronous' => true
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Here are each of the credentials for each Transbank service you will use.
    | By default, it uses the integration keys, so you can get right away with
    | mock/fake transactions. On production, you will need to issue your own.
    |
    */

    'credentials' => [
        'webpay' => [
            'key' => env('WEBPAY_KEY', Webpay::INTEGRATION_KEY),
            'secret' => env('WEBPAY_SECRET', Transbank::INTEGRATION_SECRET),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transbank response fix
    |--------------------------------------------------------------------------
    |
    | Transbank failure responses are sent as a POST request which, without a
    | cookie, will disrupt the session. The `transbank.redirect` middleware
    | can also enable an endpoint protection against brute-force attacks.
    |
    */

    'protect' => [
        'enabled' => false,
        'store' => env('TRANSBANK_PROTECT_CACHE'),
        'prefix' => 'transbank|token',
    ],
];
