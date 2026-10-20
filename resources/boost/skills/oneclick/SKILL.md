---
name: laragear-transbank-oneclick
description: "Register credit cards, authorize mall charges, and manage Oneclick subscriptions. Use when implementing 1-click payments, recurring charges, or card inscriptions. Do not use for standard one-off Webpay Plus checkouts."
---

# Oneclick Mall Subscriptions and Payments

Register customer credit cards, authorize 1-click mall payments across sub-stores, and manage saved card lifecycles. It provides `Oneclick` facade helpers and `Detail` DTOs instead of raw REST calls to Transbank's Oneclick Mall API.

## Starting card registration

Use `Oneclick::register()` with the user's username, email, and return URL instead of constructing custom form actions, because it returns a `Response` containing the inscription token and Transbank form action URL.

```php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Laragear\Transbank\Facades\Oneclick;
use Laragear\Transbank\Services\Transactions\Response;

class CardRegistrationController extends Controller
{
    public function register(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Oneclick::register(
            username: "user_{$user->getKey()}",
            email: $user->email,
            responseUrl: route('oneclick.confirm'),
        );
    }
}
```

Verification: The user receives an HTTP redirect containing the `TBK_TOKEN` query parameter to the Transbank Oneclick card inscription interface.

## Confirming and storing card inscription

Use `Oneclick::confirm()` in your callback route using the incoming `TBK_TOKEN` instead of manual token lookup, because it finalizes the inscription in Transbank and returns the permanent `tbk_user` token and card details.

```php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laragear\Transbank\Facades\Oneclick;

class CardConfirmationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $tbkToken = $request->input('TBK_TOKEN');

        if (! $tbkToken) {
            return redirect()->route('account.cards')->withErrors('Registration aborted.');
        }

        $transaction = Oneclick::confirm($tbkToken);

        if ($transaction->isSuccessful()) {
            /** @var User $user */
            $user = $request->user();

            $user->cards()->create([
                'tbk_user' => $transaction->get('tbk_user'),
                'authorization_code' => $transaction->get('authorization_code'),
                'card_type' => $transaction->get('card_type'),
                'card_number' => $transaction->getCreditCardNumber(),
            ]);

            return redirect()->route('account.cards')->with('status', 'Card added successfully!');
        }

        return redirect()->route('account.cards')->withErrors('Unable to register card.');
    }
}
```

Verification: The card inscription is completed in Transbank and the customer's permanent `tbk_user` identifier and masked card digits are stored.

## Authorizing mall charges with Detail objects

Use `Oneclick::commit()` passing `Detail` instances instead of raw nested arrays, because `Detail::make()` enforces valid types for sub-commerce codes, buy orders, amounts, and installment counts.

```php
namespace App\Services;

use App\Models\Order;
use App\Models\UserCard;
use Illuminate\Support\Str;
use Laragear\Transbank\Facades\Oneclick;
use Laragear\Transbank\Services\Transactions\Detail;
use Laragear\Transbank\Services\Transactions\TransactionDetailed;

class OneclickPaymentService
{
    public function chargeOrder(UserCard $card, Order $order): TransactionDetailed
    {
        $details = [
            Detail::make(
                commerceCode: config('services.transbank.mall_child_store_code'),
                buyOrder: Str::ulid(),
                amount: $order->total_amount,
                installments: 1,
            ),
        ];

        $response = Oneclick::commit(
            tbkUser: $card->tbk_user,
            username: "user_{$card->user_id}",
            buyOrder: Str::ulid(),
            details: $details,
        );

        if ($response->isAllSuccessful()) {
            $order->markAsPaid($response->get('buy_order'));
        }

        return $response;
    }
}
```

Verification: Transbank charges the registered card against the child commerce store and returns a `TransactionDetailed` instance whose `isAllSuccessful()` indicates full authorization.

## Deleting registered customer cards

Call `Oneclick::delete()` before deleting the local database record instead of only removing the database row, because deleting it on Transbank revokes the user token and prevents unauthorized reuse.

```php
namespace App\Http\Controllers;

use App\Models\UserCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laragear\Transbank\Facades\Oneclick;

class CardDeletionController extends Controller
{
    public function destroy(Request $request, UserCard $card): RedirectResponse
    {
        // Unregister card from Transbank servers.
        Oneclick::delete(
            tbkUser: $card->tbk_user,
            username: "user_{$request->user()->getKey()}",
        );

        // Delete from application database.
        $card->delete();

        return redirect()->route('account.cards')->with('status', 'Card removed.');
    }
}
```

Verification: Transbank revokes the card binding token, fires `RegistrationDeleted`, and the local database removes the record.
