<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\Transaction;

class TransactionCompleted
{
    /**
     * Create a new Transaction Completed event.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest  Data sent to Transbank.
     * @param  \Laragear\Transbank\Services\Transactions\Transaction  $transaction
     */
    public function __construct(public ApiRequest $apiRequest, public Transaction $transaction)
    {
        //
    }
}
