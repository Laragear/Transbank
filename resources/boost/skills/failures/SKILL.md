---
name: laragear-transbank-failures
description: "Preserve user sessions and handle Transbank POST failure redirects gracefully. Use when setting up return routes for Webpay or Oneclick to prevent session loss and CSRF errors. Do not use for normal successful GET return flows."
---

# Transaction Failure and Session Recovery

Preserve user authentication, intended URLs, and sessions when Transbank returns failed or aborted transactions. Transbank issues failure callbacks via cookie-less HTTP POST requests, which corrupts sessions and triggers CSRF exceptions unless routed through `RouteRedirect`.

## Preserving sessions using RouteRedirect

Register `RouteRedirect::as()` alongside your return routes instead of defining standard POST routes with CSRF and session middleware, because Transbank sends failed transaction callbacks via cookie-less POST requests which destroy active user sessions and fail CSRF verification.

```php
use App\Http\Controllers\PaymentConfirmationController;
use Illuminate\Support\Facades\Route;
use Laragear\Transbank\RouteRedirect;

// The GET route handles the successful transaction or redirected failure with cookies restored.
Route::get('checkout/confirm', PaymentConfirmationController::class)
    ->name('payment.confirm')
    ->middleware('transbank.protect');

// Catches the Transbank POST request, disables session/CSRF checks, and redirects to GET (303 See Other).
RouteRedirect::as('checkout/confirm');
```

Verification: Transbank POST requests on failure or cancellation are received, stripped of CSRF requirements, and redirected to the GET route where browser cookies restore the user session.

## Redirecting failure POST to a dedicated failure route

Pass a custom destination route or path as the second argument to `RouteRedirect::as()` instead of routing failed transactions to the same confirmation route, because it separates successful order consolidation logic from failure handling views.

```php
use App\Http\Controllers\PaymentConfirmationController;
use App\Http\Controllers\PaymentFailedController;
use Illuminate\Support\Facades\Route;
use Laragear\Transbank\RouteRedirect;

Route::get('checkout/confirm', PaymentConfirmationController::class)->name('payment.confirm');
Route::get('checkout/declined', PaymentFailedController::class)->name('payment.declined');

// Directs incoming POST failures from 'checkout/confirm' to 'checkout/declined'.
RouteRedirect::as('checkout/confirm', 'checkout/declined');
```

Verification: Failed or aborted transactions posting to `checkout/confirm` are automatically redirected to `checkout/declined` with Transbank query parameters intact.

## Configuring WebpayRequest validation redirects

Assign a path or container-resolved closure to `WebpayRequest::$validate` in application bootstrapping instead of writing manual query checks in controllers, because direct requests lacking valid Transbank tokens are redirected before controller execution.

```php
namespace App\Providers;

use Illuminate\Routing\Redirector;
use Illuminate\Support\ServiceProvider;
use Laragear\Transbank\Http\Requests\WebpayRequest;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Redirect unauthorized hits without Transbank tokens back to checkout.
        WebpayRequest::$validate = function (Redirector $redirect): string {
            return $redirect->route('cart.checkout');
        };
    }
}
```

Verification: Visiting the payment confirmation URL directly without `token_ws` or `TBK_TOKEN` triggers a clean redirect to the cart checkout instead of a validation exception.

## Detecting aborted transactions without committing

Check `$request->isError()` before attempting to retrieve `$request->transaction()` in controllers, because aborted transactions provide `TBK_TOKEN` and `TBK_ORDEN_COMPRA` but lack a committable `token_ws`, and calling commit on them throws an exception. On these failure-shaped requests, `buyOrder()` can only return the client-supplied `TBK_ORDEN_COMPRA`, so verify it against your application's own records before writing anything.

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Laragear\Transbank\Http\Requests\WebpayRequest;

class PaymentConfirmationController extends Controller
{
    public function __invoke(WebpayRequest $request): RedirectResponse
    {
        // When a user cancels the payment at Transbank, isError() returns true.
        if ($request->isError()) {
            Order::where('buy_order', $request->buyOrder())->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return redirect()->route('cart.show')->with('notice', 'Payment cancelled by user.');
        }

        // Safe to commit since request contains token_ws.
        if ($request->isSuccessful()) {
            return redirect()->route('orders.show', $request->buyOrder());
        }

        return redirect()->route('cart.show')->withErrors('Payment rejected.');
    }
}
```

Verification: Aborted transactions update order state without triggering an unhandled API error on commit.
