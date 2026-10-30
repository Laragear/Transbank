---
name: laragear-transbank-emulator
description: "Emulate Transbank Webpay Plus checkouts locally with selectable transaction results. Use when developing or testing Webpay payment flows offline — including Filament WebpayAction checkouts — simulating approve, reject, abort and fail outcomes, forcing GET or POST returns and alternate token parameters, or writing feature tests for checkout flows. Do not use for Oneclick transactions (including the Filament OneclickAction) or in production."
---

# Webpay Plus Local Emulator

Simulate Webpay Plus payment lifecycles entirely offline. The emulator intercepts HTTP requests to both Transbank integration and production endpoints through the Laravel HTTP client, redirects the user to an emulated Webpay payment page, and answers commits and status checks with realistic responses according to the result selected on it, instead of requiring integration servers, test credit cards, or network access.

## Enabling the emulator

Enable the emulator with `TRANSBANK_EMULATOR=true` instead of pointing the application to Transbank integration servers, because repeated integration requests risk rate-limiting and network timeouts. Both integration and production endpoints are intercepted, so `TRANSBANK_ENV` can stay as it is.

```dotenv
# .env

# Emulate Webpay Plus locally instead of hitting Transbank servers.
TRANSBANK_EMULATOR=true
```

```php
// config/transbank.php — publish with `php artisan vendor:publish --tag=config`.
return [
    'emulator' => [
        'enabled' => env('TRANSBANK_EMULATOR', false),
        'path' => 'transbank/emulator',
    ],
];
```

The emulated checkout page is served at the `transbank.emulator.path` route, which can only be changed in the published configuration file. Enabling it while the application runs in production throws a `RuntimeException` from `TransbankServiceProvider`, so it can never leak into live checkouts.

Verification: `Webpay::create()` returns a `Response` whose `getUrl()` points to the local emulator route, like `http://localhost/transbank/emulator`, and the user is redirected there with the token as `token_ws` without any external HTTP request.

## Choosing the transaction result

Select one of four results on the emulated Webpay page instead of hunting for test credit card numbers, because the emulator reproduces the exact return parameters and commit responses of each real scenario.

| Result | Emulates | Return parameters (auto) | Commit response |
| --- | --- | --- | --- |
| `approve` | Authorized payment | GET with `token_ws` | 200 — `status: AUTHORIZED`, `response_code: 0`, random `authorization_code` |
| `reject` | Declined payment | GET with `token_ws` | 200 — `status: FAILED`, `response_code: -1`, `authorization_code: "000000"` |
| `abort` | User pressed "Anular compra y volver" | GET with `TBK_TOKEN`, `TBK_ORDEN_COMPRA`, `TBK_ID_SESION` | 422 — `error_message: "Transaction has an invalid finished state: aborted"` |
| `fail` | Webpay form error mid-transaction | GET with `token_ws` plus all `TBK_*` | 422 — same aborted-state error |

The emulated card is always masked as `0000`, and the amount, buy order and session id echo the ones sent to `Webpay::create()`.

Verification: Opening the URL returned by `Webpay::create()` shows the emulated page with the transaction amount and buy order; submitting any result issues a 303 redirect to the configured return URL with the parameters above.

## Forcing return methods and token parameters

Use the "Return options" panel of the emulated page — or the `method`, `token_key` and `tbk` form fields when driving it programmatically — instead of assuming Transbank always returns by GET with a `token_ws`, because real servers occasionally return failed transactions by cookie-less POST or with alternate token casing.

- **`method`**: `auto` (default), `get` or `post`. The `post` variant renders an auto-submitting interstitial form posting to the configured return URL, like Transbank's failure POSTs.
- **`token_key`**: `auto` (default), `token_ws`, `TOKEN_WS` or `token`. With `auto`, aborted transactions return `TBK_TOKEN` and everything else `token_ws`.
- **`tbk`**: `auto` (default: included on `abort` and `fail`), `all` or `none`. Controls the `TBK_TOKEN`, `TBK_ORDEN_COMPRA` and `TBK_ID_SESION` fallback parameters.

```php
use App\Http\Controllers\PaymentReturnController;
use Illuminate\Support\Facades\Route;
use Laragear\Transbank\RouteRedirect;

// Handles normal GET returns and restored failure returns alike.
Route::get('checkout/return', PaymentReturnController::class)
    ->name('checkout.return')
    ->middleware('transbank.protect');

// Catches the POST return, skips CSRF/session, and 303-redirects to the GET route.
RouteRedirect::as('checkout/return');
```

Verification: Submitting with `method=post` posts the return parameters to `RouteRedirect`, which converts them into a 303 GET redirection to `checkout/return` with the user session intact.

## Handling emulated returns with WebpayRequest

Type-hint `WebpayRequest` in the return controller and check `isError()` before committing, because abort and fail results carry `TBK_*` parameters instead of a committable `token_ws`, and the emulator refuses to commit them with HTTP 422, just like the real servers. On these failure-shaped requests `buyOrder()` can only return the client-supplied `TBK_ORDEN_COMPRA`, so verify it against your application's own records before writing anything.

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Laragear\Transbank\Http\Requests\WebpayRequest;

class PaymentReturnController extends Controller
{
    public function __invoke(WebpayRequest $request): RedirectResponse
    {
        $order = Order::where('buy_order', $request->buyOrder())->firstOrFail();

        // Abort and fail results cannot be committed: cancel the order locally.
        if ($request->isError()) {
            $order->update(['status' => 'cancelled']);

            return redirect()->route('cart.show')->with('warning', 'Payment was cancelled.');
        }

        // Commits only once: AUTHORIZED (approve) or FAILED (reject).
        $transaction = $request->transaction();

        $order->update([
            'status' => $transaction->isSuccessful() ? 'paid' : 'declined',
            'authorization_code' => $transaction->authorization_code,
        ]);

        return redirect()->route('orders.show', $order);
    }
}
```

Verification: Approving marks the order paid; rejecting marks it declined; aborting or failing cancels it without triggering the 422 commit exception.

## Testing complete checkout flows in feature tests

Enable the emulator before the application boots — through `phpunit.xml` or `.env.testing` — instead of a `config()` call inside the test `setUp()`, because the emulator routes and HTTP fakes are registered while the service providers boot, which happens before the test setup runs.

```xml
<!-- phpunit.xml -->
<php>
    <env name="TRANSBANK_EMULATOR" value="true"/>
</php>
```

```php
namespace Tests\Feature;

use App\Models\Order;
use Laragear\Transbank\Facades\Webpay;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    public function test_user_completes_the_payment(): void
    {
        $order = Order::factory()->create(['buy_order' => 'ORDER-123', 'total' => 15000]);

        // 1. Create the transaction and take the token from the emulated response.
        $token = Webpay::create(
            buyOrder: $order->buy_order,
            amount: $order->total,
            returnUrl: route('checkout.return'),
        )->getToken();

        // 2. Simulate the user approving the payment on the emulated page.
        $this->post('transbank/emulator', [
            'token_ws' => $token,
            'result' => 'approve',
        ])->assertRedirect(route('checkout.return', ['token_ws' => $token]));

        // 3. Follow the return: the application commits and consolidates the order.
        $this->get(route('checkout.return', ['token_ws' => $token]))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_user_aborts_the_payment(): void
    {
        $order = Order::factory()->create(['buy_order' => 'ORDER-456', 'total' => 15000]);

        $token = Webpay::create(
            buyOrder: $order->buy_order,
            amount: $order->total,
            returnUrl: route('checkout.return'),
        )->getToken();

        // The abort result redirects to the return URL with TBK_* fallbacks.
        $this->post('transbank/emulator', [
            'token_ws' => $token,
            'result' => 'abort',
        ])->assertRedirect();

        // Follow the return with the TBK_* parameters and cancel the order.
        $this->get(route('checkout.return', [
            'TBK_TOKEN' => $token,
            'TBK_ORDEN_COMPRA' => $order->buy_order,
            'TBK_ID_SESION' => 'no-session-id',
        ]))->assertRedirect(route('cart.show'));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }
}
```

The emulated page is served by the named routes `transbank.emulator.show` (GET) and `transbank.emulator.submit` (POST), so prefer `route()` calls if the default path was changed.

Verification: Running `php artisan test` or `vendor/bin/phpunit` executes complete payment flows in memory without network calls, real credentials, or external services.

## Running emulated payments through Filament actions

Use the package `WebpayAction` on Filament tables and pages instead of wiring checkout controllers, because it wraps `Webpay::create()` and redirects the user away, so with the emulator enabled the action redirects to the local emulated page without any change. The closures receive the table record, and form fields named `buyOrder`, `amount` or `returnUrl` can also feed the transaction through `data()`.

```php
use App\Models\Order;
use Laragear\Transbank\Filament\WebpayAction;
use Laragear\Transbank\Services\Transactions\Response;
use Throwable;

// A record action on a Filament table, or a page action with the record at hand.
WebpayAction::make('pay')
    ->buyOrder(fn (Order $record) => $record->buy_order)
    ->amount(fn (Order $record) => $record->total)
    // The return is a full browser redirect outside Livewire: always set a plain GET route.
    ->returnUrl(fn () => route('checkout.return'))
    ->afterResponse(function (Response $response, ?Order $record) {
        $record?->update(['payment_token' => $response->getToken()]);
    })
    // Runs on create failure and reports, but the exception is rethrown anyway.
    ->rescue(fn (Throwable $exception) => report($exception));
```

Always set `returnUrl()` explicitly instead of relying on the default, because the fallback is the current full URL — the Livewire endpoint — where the returning token would never reach your `WebpayRequest` controller. The `OneclickAction` has no emulated counterpart.

In tests, call the action through Livewire and continue the emulated round-trip with the token from the redirect target:

```php
use Illuminate\Support\Str;
use Livewire\Livewire;

$url = Livewire::test(OrdersList::class) // Any component mounting the action.
    ->callAction('pay', $order)
    ->assertRedirect()
    ->headers->get('Location');

// The action redirects to the emulated page: http://localhost/transbank/emulator?token_ws=...
$token = Str::after($url, 'token_ws=');

// Drive the emulator and assert the return like any other flow.
$this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'approve']);
```

Verification: Calling the action redirects to the local emulator URL with a fresh `token_ws`, and the emulated return hits the configured plain GET route handled by `WebpayRequest`.

## Knowing the emulator limits

Keep the emulator scope in mind when designing test suites instead of expecting a complete Transbank simulation:

- **Webpay Plus only**: Only `Webpay::create()`, `Webpay::commit()` and `Webpay::status()` are emulated. Oneclick registration and charges are not, including the Filament `OneclickAction`.
- **No refunds or captures**: Any other endpoint, like `Webpay::refund()` or `Webpay::capture()`, answers HTTP 501 with `This endpoint is not emulated.`
- **5-minute tokens**: Emulated transactions live in the application cache for 300 seconds (`Store::TTL`), mirroring real Transbank tokens. Unknown or expired tokens answer HTTP 404 on the page, the commit and the status check.
- **Never on production**: Enabling the emulator while `app()->environment('production')` throws an uncatchable `RuntimeException` at boot.

Verification: Committing an unknown or expired token fails with HTTP 404, and calling an unemulated endpoint like `Webpay::refund()` fails with HTTP 501.
