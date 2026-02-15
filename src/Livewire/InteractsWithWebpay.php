<?php

namespace Laragear\Transbank\Livewire;

use Laragear\Transbank\Services\Transactions\Transaction;
use Laragear\Transbank\Services\Webpay;
use Livewire\Attributes\Url;
use Throwable;

trait InteractsWithWebpay
{
    /**
     * The Webpay Token for completed transactions.
     *
     * @var string
     */
    #[Url]
    public $token_ws = '';

    /**
     * The Webpay Token for failed transactions.
     *
     * @var string
     */
    #[Url]
    public $TBK_TOKEN = '';

    /**
     * Determines if the transaction was successful or not.
     *
     * @var bool|null
     */
    public ?bool $isSuccessful = null;

    /**
     * Handles the incoming Webpay request.
     */
    public function mountInteractsWithWebpay(Webpay $webpay): void
    {
        if ($this->isWebpayReturn()) {
            $this->beforeHandledTransaction();

            try {
                $transaction = $this->handleWebpayTransaction($webpay);
            } catch (Throwable $exception) {
                $result = $this->handleWebpayException($exception);

                // If the handler returns an exception, we will throw it as normal procedure.
                // Otherwise, we will just stop the transaction handling entirely since the
                // Transbank Transaction object doesn't exist and there is nothing to do.
                if ($result instanceof Throwable) {
                    throw $result;
                } else {
                    return;
                }
            }

            $this->afterTransactionReceived($transaction);

            if ($this->isSuccessful = $this->handleTransactionStatus($transaction)) {
                $this->handleSuccessfulTransaction($transaction);
            } else {
                $this->handleFailedTransaction($transaction);
            }

            $this->afterHandledTransaction($transaction);
        } else {
            $this->handleNonWebpayResponse();
        }
    }

    /**
     * Determine if the current request is byproduct of a Webpay transaction.
     */
    protected function isWebpayReturn(): bool
    {
        return $this->token_ws || $this->TBK_TOKEN;
    }

    /**
     * Run something before handling the Webpay Transaction.
     */
    protected function beforeHandledTransaction(): void
    {
        //
    }

    /**
     * Retrieve the Webpay Transaction.
     */
    protected function handleWebpayTransaction(Webpay $webpay): Transaction
    {
        return $webpay->commit($this->token_ws ?: $this->TBK_TOKEN);
    }

    /**
     * Handle the transaction after being received.
     */
    protected function afterTransactionReceived(Transaction $transaction): void
    {
        // ...
    }

    /**
     * Determine if the transaction is successful or not.
     */
    protected function handleTransactionStatus(Transaction $transaction): bool
    {
        return $transaction->isSuccessful();
    }

    /**
     * Handle the exception given by Webpay.
     *
     * @return \Throwable|mixed|null|void
     */
    protected function handleWebpayException(Throwable $exception)
    {
        return $exception;
    }

    /**
     * Handle a successful transaction.
     */
    abstract protected function handleSuccessfulTransaction(Transaction $transaction): void;

    /**
     * Handle a failed transaction
     */
    protected function handleFailedTransaction(Transaction $transaction): void
    {
        //
    }

    /**
     * Execute something after the transaction has been handled.
     */
    protected function afterHandledTransaction(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle a response that does not come from Webpay.
     */
    protected function handleNonWebpayResponse(): void
    {
        //
    }
}
