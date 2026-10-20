---
name: laragear-transbank-livewire
description: "Integrate Webpay checkout returns and Oneclick card registrations into Livewire components. Use when building interactive Livewire payment result pages or subscription dashboards. Do not use in traditional synchronous HTTP controllers."
---

# Livewire Payment and Inscription Integration

Handle Webpay payment returns and Oneclick card inscriptions inside Livewire components. The `InteractsWithWebpay` and `InteractsWithOneclick` traits bind incoming query parameters (`token_ws`, `TBK_TOKEN`), run commit/confirm lifecycle hooks during component mount, and trigger dedicated success/failure handlers.

## Handling Webpay transactions with InteractsWithWebpay

Use the `InteractsWithWebpay` trait in your Livewire component and implement `handleSuccessfulTransaction()` instead of parsing query parameters in `mount()`, because the trait binds `#[Url]` tokens, executes transaction commit automatically, and sets `$this->isSuccessful`.

```php
namespace App\Livewire;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Laragear\Transbank\Livewire\InteractsWithWebpay;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;

class PaymentStatus extends Component
{
    use InteractsWithWebpay;

    public ?Order $order = null;

    protected function afterTransactionReceived(Transaction $transaction): void
    {
        if ($buyOrder = $transaction->get('buy_order')) {
            $this->order = Order::where('buy_order', $buyOrder)->first();
        }
    }

    protected function handleSuccessfulTransaction(Transaction $transaction): void
    {
        $this->order?->markAsPaid([
            'authorization_code' => $transaction->authorization_code,
            'card_number' => $transaction->getCreditCardNumber(),
        ]);
    }

    protected function handleFailedTransaction(Transaction $transaction): void
    {
        $this->order?->markAsFailed();
    }

    public function render(): View
    {
        return view('livewire.payment-status');
    }
}
```

Verification: Visiting the Livewire route with `token_ws` automatically commits the transaction, invokes `handleSuccessfulTransaction()` or `handleFailedTransaction()`, and reflects `$this->isSuccessful` in the view.

## Handling exceptions and customizing status checks

Override `handleWebpayException()` and `handleTransactionStatus()` instead of placing try-catch blocks in views or controllers, because it intercepts Transbank network or client errors gracefully and allows custom business validation (such as checking matching amounts).

```php
namespace App\Livewire;

use App\Models\Order;
use Laragear\Transbank\Exceptions\ClientException;
use Laragear\Transbank\Livewire\InteractsWithWebpay;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;
use Throwable;

class CheckoutStatus extends Component
{
    use InteractsWithWebpay;

    public ?Order $order = null;
    public string $errorMessage = '';

    protected function handleTransactionStatus(Transaction $transaction): bool
    {
        // Require both Transbank authorization and matching order amounts.
        return $transaction->isSuccessful()
            && (float) $transaction->get('amount') === (float) $this->order?->total_amount;
    }

    protected function handleWebpayException(Throwable $exception): mixed
    {
        if ($exception instanceof ClientException) {
            $this->isSuccessful = false;
            $this->errorMessage = 'The transaction was cancelled or timed out.';

            return false; // Suppress exception throwing to render error state.
        }

        report($exception);
        $this->errorMessage = 'Transbank is currently unreachable. Please try again.';

        return false;
    }

    protected function handleSuccessfulTransaction(Transaction $transaction): void
    {
        $this->order->markAsPaid($transaction->authorization_code);
    }
}
```

Verification: Exceptions like timeout or user cancellation are caught, suppressing component crashes and populating `$errorMessage` for user feedback.

## Handling Oneclick card registrations with InteractsWithOneclick

Use `InteractsWithOneclick` and implement `handleSuccessfulRegistration()` instead of writing manual registration confirmation in Livewire, because it binds `TBK_TOKEN` and runs confirmation on mount.

```php
namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laragear\Transbank\Livewire\InteractsWithOneclick;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Component;

class CardRegistrationStatus extends Component
{
    use InteractsWithOneclick;

    public string $message = '';

    protected function handleSuccessfulRegistration(Transaction $transaction): void
    {
        Auth::user()->cards()->create([
            'tbk_user' => $transaction->get('tbk_user'),
            'authorization_code' => $transaction->get('authorization_code'),
            'card_type' => $transaction->get('card_type'),
            'card_number' => $transaction->getCreditCardNumber(),
        ]);

        $this->message = 'Your credit card has been saved!';
    }

    protected function handleFailedRegistration(Transaction $transaction): void
    {
        $this->message = 'Card registration was rejected or aborted.';
    }

    public function render(): View
    {
        return view('livewire.card-registration-status');
    }
}
```

Verification: Transbank return with `TBK_TOKEN` confirms the card registration on mount and runs `handleSuccessfulRegistration()` to save card details.
