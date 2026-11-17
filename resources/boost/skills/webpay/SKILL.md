---
name: laragear-transbank-webpay
description: "Initiate, confirm, inspect, and refund Webpay Plus transactions. Use when creating standard Webpay checkouts or processing return callbacks. Do not use when handling Oneclick subscriptions or recurring automatic charges."
---

# Webpay Plus Payments

Initiate, commit, and manage Webpay Plus payment lifecycles. It provides convenient `Webpay` facade methods, automatic HTTP redirection via `Responsable` responses, and safe single-commit verification through `WebpayRequest` instead of manual token parsing or official SDK wrappers.

## Creating a payment transaction

Use `Webpay::create()` with a unique buy order identifier and return the `Response` directly instead of manually building redirect forms or querying API endpoints, because the `Response` instance implements `Responsable` and automatically handles client redirection to Transbank.

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Services\Transactions\Response;

class PaymentController extends Controller
{
    public function checkout(Request $request, Order $order): Response
    {
        // Transbank requires buy orders up to 26 characters.
        $buyOrder = Str::ulid();

        $order->update(['buy_order' => $buyOrder]);

        return Webpay::create(
            buyOrder: $buyOrder,
            amount: $order->total_amount,
            returnUrl: route('payment.confirm'),
        );
    }
}
```

Verification: The controller returns an automatic HTTP redirect sending the user to Transbank Webpay with a generated transaction token.

## Confirming transactions safely with WebpayRequest

Type-hint `WebpayRequest` in your return controller method instead of standard `Request`, and call `$request->isSuccessful()` before accessing transaction data, because `WebpayRequest` safely commits the token only once and handles aborted or failed transactions without throwing unhandled exceptions. When the request is failure-shaped, `buyOrder()` can only return the client-supplied `TBK_ORDEN_COMPRA`, so verify it against your application's own records before writing anything.

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Laragear\Transbank\Http\Requests\WebpayRequest;

class PaymentConfirmationController extends Controller
{
    public function __invoke(WebpayRequest $request): RedirectResponse
    {
        $order = Order::where('buy_order', $request->buyOrder())->firstOrFail();

        if ($request->isSuccessful()) {
            $transaction = $request->transaction();

            $order->markAsPaid([
                'authorization_code' => $transaction->authorization_code,
                'card_number' => $transaction->getCreditCardNumber(),
                'payment_type' => $transaction->payment_type_code,
                'paid_at' => now(),
            ]);

            return redirect()->route('orders.show', $order)->with('status', 'Payment successful!');
        }

        $order->markAsFailed();

        return redirect()->route('checkout.show')->withErrors('Payment was declined or aborted.');
    }
}
```

Verification: The code safely checks payment status, commits the token on Webpay servers without double-commit collisions, and updates the local order state accordingly.

## Committing conditionally before final charge

Use `$request->commitWhen()` or `$request->commitUnless()` on `WebpayRequest` instead of querying order records after committing, because it evaluates pre-commit constraints against transaction status before charging the user's card. If the request turns out failure-shaped, the buy order used for the lookup is client-supplied, so verify it against your application's own records before writing anything.

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Laragear\Transbank\Http\Requests\WebpayRequest;
use Laragear\Transbank\Services\Transactions\Transaction;

class ConditionalPaymentController extends Controller
{
    public function __invoke(WebpayRequest $request): RedirectResponse
    {
        $order = Order::where('buy_order', $request->buyOrder())->firstOrFail();

        // Commit only if inventory is still available and order is pending.
        $transaction = $request->commitWhen(function (Transaction $status) use ($order): bool {
            return $order->isPending() && $order->hasSufficientStock() && $status->amount === (float) $order->total_amount;
        });

        if ($transaction?->isSuccessful()) {
            $order->finalizePayment($transaction->authorization_code);

            return redirect()->route('orders.show', $order);
        }

        return redirect()->route('checkout.show')->withErrors('Unable to complete transaction.');
    }
}
```

Verification: Webpay only executes the transaction commit if the condition evaluates to true, preventing charges when checkout criteria change.

## Refunding and capturing authorized payments

Use `Webpay::refund()` or `Webpay::capture()` via the `Webpay` facade instead of manual HTTP requests, because the service resolves the correct REST endpoints, logs transaction diagnostics, and fires package events.

When refunding, Transbank returns different response shapes depending on when the refund is made:
- **Same-day full refund**: returns `{"type": "REVERSED"}` with no `response_code`. `isSuccessful()` returns `true`.
- **Next-day or later refund**: returns `{"type": "NULLIFIED", "response_code": 0, "balance": 0, ...}`. `isSuccessful()` returns `true`.
- **Failed refund**: returns `{"error_message": "..."}` (e.g., "Amount to refund is bigger than authorized" or "Transaction already REVERSED"). `isSuccessful()` returns `false`.

Always check `$transaction->isSuccessful()` after calling `refund()` — both REVERSED and NULLIFIED responses are treated as successful.

```php
namespace App\Services;

use App\Models\Order;
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Services\Transactions\Transaction;

class WebpayManagementService
{
    public function refundOrder(Order $order, float $amount): Transaction
    {
        // Refund partially or totally a committed payment.
        // Same-day refunds return {"type": "REVERSED"}; later refunds return {"type": "NULLIFIED", ...}.
        // Both are treated as successful by isSuccessful().
        return Webpay::refund($order->payment_token, $amount);
    }

    public function captureDeferredPayment(Order $order, int $authCode, float $amount): Transaction
    {
        // Capture an authorized credit-card charge held up to 7 days.
        return Webpay::capture(
            token: $order->payment_token,
            buyOrder: $order->buy_order,
            code: $authCode,
            amount: $amount,
        );
    }
}
```

Verification: The refund or capture operation completes on Transbank servers and returns a `Transaction` object detailing the operation response.
