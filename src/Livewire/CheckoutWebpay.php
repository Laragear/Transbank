<?php

namespace Laragear\Transbank\Livewire;

use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Services\Transactions\Transaction;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

abstract class CheckoutWebpay extends Component
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

    public function mount(): void
    {
        if ($this->isWebpayReturn()) {
            $this->beforeHandledTransaction();

            try {
                $transaction = $this->handleWebpayTransaction();
            } catch (Throwable $exception) {
                $result = $this->handleWebpayException($exception);

                throw ($result instanceof Throwable ? $result : $exception);
            }

            if ($this->isSuccessful = $transaction->isSuccessful()) {
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
    protected function handleWebpayTransaction(): Transaction
    {
        return Webpay::commit($this->token_ws ?: $this->TBK_TOKEN);
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
