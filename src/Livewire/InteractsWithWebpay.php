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
    public ?string $token_ws = null;

    /**
     * The Webpay Token for failed transactions.
     *
     * @var string
     */
    #[Url]
    public ?string $TBK_TOKEN = null;

    /**
     * The Buy Order for failed transactions (returned as fallback for aborted/failed transactions)
     *
     * @var string
     */
    #[Url]
    public ?string $TBK_ORDEN_COMPRA = null;

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

                // If the result is falsy (null, false, etc.), just return and do nothing else.
                if (!$result) {
                    return;
                }

                throw $result instanceof Throwable ? $result : $exception;
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
     */
    protected function handleWebpayException(Throwable $exception): mixed
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
