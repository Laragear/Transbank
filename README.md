# Transbank
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/transbank.svg)](https://packagist.org/packages/laragear/transbank)
[![Latest stable test run](https://github.com/Laragear/Transbank/workflows/Tests/badge.svg)](https://github.com/Laragear/Transbank/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/Transbank/branch/1.x/graph/badge.svg?token=LKnve3PkRl)](https://codecov.io/gh/Laragear/Transbank)
[![Maintainability](https://api.codeclimate.com/v1/badges/8428413a7e0fd9feb57f/maintainability)](https://codeclimate.com/github/Laragear/Transbank/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Transbank&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Transbank)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/9.x/octane#introduction)

Easy-to-use Transbank SDK for PHP for Webpay, Webpay Mall and Oneclick Mall.

```php
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Http\Requests\WebpayRequest;

public function pay(Request $request)
{
    return Webpay::create('pink teddy bear', 1990, url('confirm'));
}

public function confirm(WebpayRequest $payment)
{
    if ($payment->isSuccessful()) {
        return 'Your pink teddy bear is on the way!';
    };
}
```

> Only supports Webpay at the moment. Webpay Mall and Oneclick Mall are planned based on support.

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FReCaptcha&hashtags=PHP,Laravel,Transbank,WebPay)**

## Requisites:

* Laravel 9.x, or later
* PHP 8.0 or later

# Installation

You can install the package via Composer:

```shell
composer require laragear/transbank
```

## Usage

This SDK mimics all the Webpay methods from the [official Transbank SDK for PHP](https://github.com/TransbankDevelopers/transbank-sdk-php). 

You can check the documentation of these services in Transbank Developer's site.

- [Webpay](https://www.transbankdevelopers.cl/documentacion/webpay-plus#webpay-plus)

## Quickstart

Use the service facade you want to make a payment for. 

For example, to make a payment request, use `Webpay::create()`, along with the URL to return to your application once the payment is done.

```php
use Laragear\Transbank\Facades\Webpay;

public function pay(Request $request)
{
    return Webpay::create('pink teddy bear', 1990, route('confirm'));
}
```

Once done, you can confirm the payment using the convenient `WebpayRequest` in your controller.

```php
use Laragear\Transbank\Http\Requests\WebpayRequest;

public function confirm(WebpayRequest $request)
{
    $transaction = $request->transaction();
    
    if ($transaction->isSuccessful()) {
        return 'Your pink teddy bear is on the way!';
    };
}
```

## Environments and credentials

By default, this SDK starts up in **integration** environment, where all transactions made are fake by using Transbank's own _integration_ server, and it comes with integration credentials.

Transbank will give you production credentials for each service you have contracted. You can them set them conveniently using the `.env` file.

```dotenv
WEBPAY_KEY=597055555532
WEBPAY_SECRET=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C
```

To operate in production mode, where all transaction will be real, you will need set the environment to `production` **explicitly** in using your `.env` environment file.

```dotenv
TRANSBANK_ENV=production
```

> Production keys don't work on _integration_ and vice versa. 

## Middleware endpoint protection

You may want to use the included `transbank.protect` middleware to validate the transaction response from Transbank (the route which Transbank returns the user to). It will void any request without the proper tokens.

```php
use Illuminate\Support\Facades\Route;

Route::get('confirm', function (WebpayRequest $request) {
    // ...
})->middleware('transbank.handle')
```

Additionally, you can enable [endpoint protection](#endpoint-protection) to only let Transbank requests to be allowed into the application.

## Transaction Failure Middleware

Transbank failure responses for transactions are sent using a `POST` request. **This disrupts the session** because these come back without cookies, hence a new empty session is generated. This renders authentication useless and loses refers or intended URLs. 

To avoid that, use the convenient `RouteRedirect` facade to create a ready-made route that handles the `POST` failure request back to your application. When this redirection is processed, your browser sends its cookies to the application, recovering the session.

```php
use Illuminate\Support\Facades\Route;
use Laragear\Transbank\Http\Requests\WebpayRequest;
use Laragear\Transbank\Facades\RouteRedirect;

Route::get('confirm', function (WebpayRequest $request) {
    // ...
})->middleware('transbank.protect');

RouteRedirect::as('confirm');
```

By default, the redirection uses the same path, but you can change it using a second parameter.

```php
use Illuminate\Support\Facades\Route;
use Laragear\Transbank\Http\Requests\WebpayRequest;
use Laragear\Transbank\Facades\RouteRedirect;

Route::get('confirm', function (WebpayRequest $request) {
    // ... Handle the successful transaction.
})->middleware('transbank.protect');

Route::get('failed-transaction', function () {
    // ... Handle the failed transaction.
})->middleware('transbank.protect');

RouteRedirect::as('confirm', 'failed-transaction');
```

> If you're using a different middleware to verify CSRF tokens, set the class in `RouteRedirect::$csrfMiddleware`.  

## Events

You will be able to hear all transactions started and completed. This package sends the following events:

* `TransactionCreating` before a transaction is created in Transbank.
* `TransactionCreated` after a transaction is created in Transbank, but pending payment.
* `TransactionCompleted` after a transaction or refund is completed in Transbank, regardless of the success.

## Exceptions

All exceptions implement `TransbankException`, so you can easily catch and check what happened.

> Transactions properly rejected by banks or credit card issuers **do not** throw exceptions.

There are 4 types of exceptions:

* `ClientException`: Any error byproduct of bad transactions, misconfiguration, aborts, abandonment, timeout or invalid values.
* `ServerException`: Any internal Transbank servers errors.
* `NetworkException`: Any communication error from Transbank Server, like network timeouts or wrong endpoints.
* `UnknownException`: Any other error.

## Advanced configuration

There is a handy configuration file you can use if you need nitpicking. Publish it with Artisan:

```shell
php artisan vendor:publish --provider="Laragear\Transbank\TransbankServiceProvider" --tag="config"
```

You will receive the `config/transbank.php` file with the following contents:

```php
<?php

return [
    'environment' => env('TRANSBANK_ENV'),
    'http' => [
        'timeout' => 10,
        'retries' => 3,
        'options' => [
            'synchronous' => true
        ]
    ],
    'credentials' => [
        // ...
    ],
    'protect' => [
        'enabled' => false,
        'store' => env('TRANSBANK_PROTECT_CACHE'),
        'prefix' => 'transbank|token',
    ],
]
```

### Environment

```php
return [
    'environment' => env('TRANSBANK_ENV'),
]
```

To use this package on production environment, you will have to explicitly enable it using `production`. To do that, use your `.env` file.

```dotenv
TRANSBANK_ENV=production
```

This will instruct the package to use the production server for Transbank services. You should use this in combination with your [production credentials](#credentials).

### HTTP Client

```php
return [
    'http' => [
        'timeout' => 10,
        'retries' => 3,
        'options' => [
            'synchronous' => true
        ]
    ],
]
```

This array handles how much time to wait per request made to Transbank, how many retries, and any other raw option to pass to the underlying Guzzle HTTP Client.

### Credentials

```php
return [
    'credentials' => [
        // ...
    ],
]
```

This array holds each pair of credentials (key & secret) for each service. This package comes with integration credentials already set, so you can get right away on development and testing.  

### Endpoint protection

```php
return [
    'protect' => [
        'enabled' => false,
        'store' => env('TRANSBANK_PROTECT_CACHE'),
        'prefix' => 'transbank|token',
    ],
]
```

Disabled by default, you can further protect your endpoints using the [`transbank.protect` middleware](#middleware-endpoint-protection). Once enabled, it will save the token of every transaction created by 5 minutes, and once Transbank returns the user with the token, abort the request if it was not generated or was expired.

This also handles which cache store to use, and which prefix to use when storing the tokens into the cache.

# Licence

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2022 Laravel LLC.

`Redcompra`, `Webpay`, `Oneclick`, `Onepay`, `Patpass` and `Transbank` are trademarks of [Transbank S.A.](https://www.transbank.cl/). This package and its author are not associated with Transbank S.A.
