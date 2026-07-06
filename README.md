# Transbank
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/transbank.svg)](https://packagist.org/packages/laragear/transbank)
[![Latest stable test run](https://github.com/Laragear/Transbank/workflows/Tests/badge.svg)](https://github.com/Laragear/Transbank/actions)
[![Codecov Coverage](https://codecov.io/gh/Laragear/Transbank/graph/badge.svg?token=LKnve3PkRl)](https://codecov.io/gh/Laragear/Transbank)
[![Maintainability](https://qlty.sh/badges/c6975072-d953-4d0a-ab6a-1c4e475db2f5/maintainability.svg)](https://qlty.sh/gh/Laragear/projects/Transbank)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Transbank&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Transbank)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/13.x/octane#introduction)

Easy-to-use Transbank SDK for PHP for Webpay, Webpay Mall, and Oneclick Mall.

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

> [!NOTE]
>
> Only supports Webpay and Oneclick Mall at the moment. Other services are planned based on support.

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date, and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FReCaptcha&hashtags=PHP,Laravel,Transbank,WebPay)**

## Requisites

* PHP 8.3 or later
* Laravel 12 or later

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

For example, to make a payment request, use `Webpay::create()`, along with the URL to return to your application once the payment is done. The order should be a unique string with a 26-character maximum (like an ULID) to identify the transaction.

```php
use Illuminate\Support\Str;
use Laragear\Transbank\Facades\Webpay;

public function pay()
{
    $order = Str::ulid();

    return Webpay::create($order, 1990, route('confirm'));
}
```

Once done, you can confirm the payment using the convenient `WebpayRequest` in your controller.

This request object exposes the `isSuccessful()` method to check if the transaction was successful or failed by any reason, and the `buyOrder()` method to retrieve the buy order set by your application. 

```php
use Laragear\Transbank\Http\Requests\WebpayRequest;

public function confirm(WebpayRequest $request)
{
    if ($request->isSuccessful()) {
        $order = $request->buyOrder();
    
        return "Your payment was successful! Follow your order as #$order.";
    };
    
    return 'Your payment failed. Try again!'
}
```

### Checking the transaction status

When the WebPay Request is received by your application, the `TBK_TOKEN` query parameter will be sent to your application if the transaction resulted in an error, along the `TBK_ORDEN_COMPRA` query parameter for the `buy_order` you have set for your transaction.

Because Transbank may return an error if the transaction was aborted, failed, or invalid, it's imperative to use `isSuccessful()` before retrieving a failed transaction that may yield an exception by the library.

```php
use Laragear\Transbank\Http\Requests\WebpayRequest;
use App\Models\Checkout;
use App\Events\CheckoutPaid;

public function confirm(WebpayRequest $request)
{
    if ($request->isSuccessful()) {
        // Assume the transaction was successful, consolidate the checkout.
        CheckoutPaid::dispatch($request->buyOrder(), $request->transaction())
        
        // ...
    };
    
    // Assume the transaction failed.
    Checkout::whereKey($request->buyOrder())->update([
        'failed_at' => now()
    ]);
    
    // ...
}
```

> [!IMPORTANT]
> 
> When Transbank returns an `422` "Aborted" Response, this will not be rendered as an exception but a normal response. This allows your application to receive the errored response, retrieve the payment session, and act accordingly. 

### Validating Transbank Requests

If a user or bot hits your "return" URL without the transaction token, an exception will be thrown by your application. To avoid this, you may enable validation to redirect the browser to your application home or any other given relative path. You may enable this in your `bootstrap/app.php` or `AppServiceProvider` at boot time.

```php
use Illuminate\Foundation\Application;
use Laragear\Transbank\Http\Requests\WebpayRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->booted(function (): void {
        WebpayRequest::$validate = '/payment/path';
    })->create();
```

Alternatively, you may control the path the user should be redirected to using a callback. Since the callback is resolved by the Service Container, you may type-hint the current Request or any other service you need.

```php
use Illuminate\Routing\Redirector;
use Laragear\Transbank\Http\Requests\WebpayRequest;

WebpayRequest::$validate = function (Redirector $redirect) {
    return $redirect->route('payments.webpay')
};
```

## Environments and credentials

By default, this SDK starts up in **integration** environment, where all transactions made are fake by using Transbank's own _integration_ server, and it comes with integration credentials.

Transbank will give you production credentials for each service you have contracted. You can them set them conveniently using the `.env` file.

```dotenv
WEBPAY_KEY=597055555532
WEBPAY_SECRET=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C
```

To operate in production mode, where all transactions will be real, you will need to set the environment to `production` **explicitly** in using your `.env` environment file.

```dotenv
TRANSBANK_ENV=production
```

> [!NOTE]
>
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

> [!IMPORTANT]
>
> If you're using you own middleware to verify CSRF/XSRF tokens, set the class in `RouteRedirect::$csrfMiddleware`.  

## Events

You will be able to hear all transactions started and completed. This package sends the following events:

* `TransactionCreating` before a transaction is created in Transbank.
* `TransactionCreated` after a transaction is created in Transbank, but pending payment.
* `TransactionCompleted` after a transaction or refund is completed in Transbank, regardless of the success.

## Livewire Component

This package includes the [`Laragear/Transbank/Livewire/InteractsWithWebpay`](src/Livewire/InteractsWithWebpay.php) Liveware trait that automatically handles receiving a Webpay Transaction from Transbank [thanks to lifecycle hooks](https://livewire.laravel.com/docs/4.x/lifecycle-hooks#using-hooks-inside-a-trait).

This also includes the [`Laragear/Transbank/Livewire/InteractsWithOneclick`](src/Livewire/InteractsWithOneclick.php) Livewire trait to handle incoming registration attempts from Transbank.

The only requirement is to implement the `handleSuccessfulTransaction()` method, which receives the successful transaction or registration. For example, you may use this to mark a hypothetical "Cart" model as paid. 

```php
use App\Models\Checkout;
use Illuminate\Contracts\View\View;
use Laragear\Transbank\Livewire\InteractsWithWebpay;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;

class Payment extends Component
{
    use InteractsWithWebpay;
    
    protected function handleSuccessfulTransaction(Transaction $transaction) : void
    {
        $checkout = Checkout::find($transaction->session_id);
        
        $checkout->markAsPaid();
    }
    
    public function render(): View 
    {
        return view('cart.payment.status');
    }
} 
```

For the case of Oneclick registration, you should implement the `handleSuccessfulRegistration()` in your component.

```php
use App\Models\Checkout;
use Illuminate\Contracts\View\View;
use Laragear\Transbank\Livewire\InteractsWithOneclick;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;

class Subscription extends Component
{
    use InteractsWithOneclick;
    
    protected function handleSuccessfulTransaction(Transaction $transaction) : void
    {
        auth()->user()->card()->updateOrCreate([
            'transbank_user' => $transaction->get('tbk_user') 
            'card_type' => $transaction->get('card_type'),
            'card_number' => $transaction->getCreditCardNumber(), 
        ]);
    }
    
    public function render(): View 
    {
        return view('cart.subscription.status');
    }
} 
```

Apart from the `$isSuccessful` property to check the transaction success, this trait also offers other methods you can override for your convenience:

- `handleWebpayException()`: Controls exceptions thrown by Webpay (like connection errors) or silences it.
- `afterTransactionReceived()`: Handles the freshly retrieved transaction.
- `handleTransactionStatus()`: Handles how the transaction should be considered successful or failed.
- `handleFailedTransaction()`: Handles the Transaction when it has failed.
- `afterHandledTransaction()`: Handles the Transaction after failure or success.
- `handleNonWebpayResponse()`: Handles the component if no transaction was retrieved from Webpay.

> [!NOTE]
> 
> Similar methods are availble for the `InteractsWithOneclick` trait, like `handleNonOneclickResponse()`. 

You can use these methods to show different messages to the user. For example, you can use `handleNonWebpayResponse()` to redirect the user back to the cart checkout route, or `handleFailedTransaction()` to store the failure for analytics.

The `handleWebpayException()` receives any exception, like connection errors or form aborts, and lets you handle it as you wish. For example, you may suppress the exception and render your component as not-successful. Otherwise, any Exception returned will be thrown as usual, so it's great to _replace_ exceptions with your own or modify the component view.

```php
use Laragear\Transbank\Exceptions\ClientException;
use Throwable;

public $title = '';
public $message = '';

protected function handleWebpayException(Throwable $exception)
{
    if ($exception instanceof ClientException) {
        $this->isSuccessful = false;
    } else {
        report($exception);
        
        $this->title = 'Transbank is unresponsive';
        $this->message = 'We will look into it as soon as possible.';
    }
}
```

The `afterTransactionReceived()` method is great to override when you require doing logic _after_ the transaction has been received, but _before_ any other logic. For example, to retrieve a Checkout process.

```php
use App\Models\Checkout;
use Laragear\Transbank\Services\Transactions\Transaction;

public ?Checkout $checkout = null;

protected function afterTransactionReceived(Transaction $transaction): void
{
    if ($order = $transaction->get('buy_order')) {
        $this->checkout = Checkout::find($order)    
    }
}
```

Additionally, you can override `handleTransactionStatus()` method for additional transaction checks, as its result will be stored in the `$isSuccessful` property. For example, you may deem the transaction failed if the payment does not match the Checkout amount.

```php
use Filament\Notifications\Notification;
use Laragear\Transbank\Services\Transactions\Transaction;

public ?Checkout $checkout = null;

public function handleTransactionStatus(Transaction $transaction): bool
{
    return $transaction->isSuccessful()
        && $transaction->get('amount') === $this->chekout?->amount
}

protected function handleSuccessfulTransaction(Transaction $transaction) : void
{
    // Since the transaction was successful, the checkout exists.
    $this->checkout->markAsPaid();
    
    Notification::make('paid')
        ->title('Payment successful!')
        ->body('We will prepare your purchase as soon as possible.')
        ->send();
}
```

### Filament PHP Action

If you use Filament PHP, you can use the [`Laragear\Transbank\Filament\WebpayAction`](src/Filament/WebpayAction.php) and [`Laragear\Transbank\Filament\OneclickAction](src/Filament/OneclickAction.php) [Filament Actions](https://filamentphp.com/docs/5.x/actions/overview) to show a button that automatically creates a payment and redirects the user to Transbank for the payment flow.

You should use this [adding custom actions](https://filamentphp.com/docs/5.x/resources/editing-records#custom-actions), or anywhere you want. Simple use the `data()` or the methods `buyOrder()`, `amount()` and `returnUrl()` to set the minimum data to create a Transaction in Webpay servers, or `username()`, `email()` and `returnUrl()` for a Oneclick registration.

```php
use App\Models\Checkout;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;
use Laragear\Transbank\Filament\OneclickAction;use Laragear\Transbank\Filament\WebpayAction;

public ?Checkout $checkout = null;

public function mount()
{
    $this->chekout = Checkout::find(session('current_checkout_id'));
}

protected function getFormActions(): array
{
    return [
        ...parent::getFormActions(),
        WebpayAction::make('pay')
            ->label("Pay " . Number::currency($this->checkout->amount))
            ->data([
                'buyOrder' => $this->checkout->asUlid(),            
                'amount' => $this->checkout->total(),            
                'returnUrl' => action('App\Filament\Checkout\Pages\Checkout'),            
            ]),
        OneclickAction::make('register')
            ->label('Pay automatically')
            ->data([
                'username' => auth()->user()->name,
                'email' => auth()->user()->email,
                'returnUrl' => action('App\Filament\Checkout\Pages\Register')
            ])
    ];
}
```

When clicking the `Pay with Webpay` button, a redirection will be returned so the user immediately start the payment flow.

For the case of the `OneclickAction`, a modal will be rendered to manually redirect the user to Transbank using a `POST` redirection. 

## Exceptions

All exceptions implement `TransbankException`, so you can catch and check what happened.

> [!IMPORTANT]
>
> Transactions properly rejected by banks or credit card issuers **do not** throw exceptions.

There are 4 types of exceptions:

* `ClientException`: Any error byproduct of bad transactions, misconfiguration, aborts, abandonment, timeout, or invalid values.
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

This also handles which cache store to use and which prefix to use when storing the tokens into the cache.

# Licence

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at the time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011–2026 Laravel LLC.

`Redcompra`, `Webpay`, `Oneclick`, `Onepay`, `Patpass` and `Transbank` are trademarks of [Transbank S.A.](https://www.transbank.cl/). This package and its author are not associated with Transbank S.A.
