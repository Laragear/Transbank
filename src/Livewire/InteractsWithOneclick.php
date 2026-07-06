<?php

namespace Laragear\Transbank\Livewire;

use Laragear\Transbank\Services\Oneclick;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Attributes\Url;
use Throwable;

trait InteractsWithOneclick
{
    /**
     * The Oneclick Token for transactions.
     *
     * @var string|null
     */
    #[Url]
    public ?string $TBK_TOKEN = null;

    /**
     * Determines if the transaction was successful or not.
     *
     * @var bool|null
     */
    public ?bool $isSuccessful = null;

    /**
     * Handles the incoming Webpay request.
     */
    public function mountInteractsWithOneclick(Oneclick $oneclick): void
    {
        if ($this->isOneclickReturn()) {
            $this->beforeHandledRegistration();

            try {
                $transaction = $this->handleOneclickRegistration($oneclick);
            } catch (Throwable $exception) {
                $result = $this->handleOneclickException($exception);

                // If the result is falsy (null, false, etc.), just return and do nothing else.
                if (!$result) {
                    return;
                }

                throw $result instanceof Throwable ? $result : $exception;
            }

            $this->afterRegistrationReceived($transaction);

            if ($this->isSuccessful = $this->handleRegistrationStatus($transaction)) {
                $this->handleSuccessfulRegistration($transaction);
            } else {
                $this->handleFailedRegistration($transaction);
            }

            $this->afterHandledRegistration($transaction);
        } else {
            $this->handleNonOneclickResponse();
        }
    }

    /**
     * Determine if the current request is byproduct of a Oneclick transaction.
     */
    protected function isOneclickReturn(): bool
    {
        return (bool) $this->TBK_TOKEN;
    }

    /**
     * Run something before handling the Oneclick Transaction.
     */
    protected function beforeHandledRegistration(): void
    {
        //
    }

    /**
     * Retrieve the Oneclick Transaction.
     */
    protected function handleOneclickRegistration(Oneclick $oneclick): Transaction
    {
        return $oneclick->confirm($this->TBK_TOKEN);
    }

    /**
     * Handle the transaction after being received.
     */
    protected function afterRegistrationReceived(Transaction $transaction): void
    {
        // ...
    }

    /**
     * Determine if the transaction is successful or not.
     */
    protected function handleRegistrationStatus(Transaction $transaction): bool
    {
        return $transaction->isSuccessful();
    }

    /**
     * Handle the exception given by Oneclick.
     */
    protected function handleOneclickException(Throwable $exception): mixed
    {
        return $exception;
    }

    /**
     * Handle a successful transaction.
     */
    abstract protected function handleSuccessfulRegistration(Transaction $transaction): void;

    /**
     * Handle a failed transaction
     */
    protected function handleFailedRegistration(Transaction $transaction): void
    {
        //
    }

    /**
     * Execute something after the transaction has been handled.
     */
    protected function afterHandledRegistration(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle a response that does not come from Oneclick.
     */
    protected function handleNonOneclickResponse(): void
    {
        //
    }
}
