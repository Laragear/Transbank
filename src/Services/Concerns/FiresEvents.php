<?php

namespace Laragear\Transbank\Services\Concerns;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Events\TransactionCompleted;
use Laragear\Transbank\Events\TransactionCreated;
use Laragear\Transbank\Events\TransactionCreating;
use Laragear\Transbank\Services\Transactions\Response;
use Laragear\Transbank\Services\Transactions\Transaction;

trait FiresEvents
{
    /**
     * Fires a Transaction Started event.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     */
    protected function fireCreating(ApiRequest $apiRequest): void
    {
        $this->event->dispatch(new TransactionCreating($apiRequest));
    }

    /**
     * Fires a Transaction Created event.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     * @param  \Laragear\Transbank\Services\Transactions\Response  $response
     */
    protected function fireCreated(ApiRequest $apiRequest, Response $response): void
    {
        $this->event->dispatch(new TransactionCreated($apiRequest, $response));
    }

    /**
     * Fires a Transaction Completed event.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     * @param  \Laragear\Transbank\Services\Transactions\Transaction  $transaction
     */
    protected function fireCompleted(ApiRequest $apiRequest, Transaction $transaction): void
    {
        $this->event->dispatch(new TransactionCompleted($apiRequest, $transaction));
    }
}
