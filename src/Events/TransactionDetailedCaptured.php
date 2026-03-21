<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\TransactionDetailed;

readonly class TransactionDetailedCaptured
{
    /**
     * Create a new Oneclick Charging instance.
     */
    public function __construct(public ApiRequest $apiRequest, public TransactionDetailed $transaction)
    {
        //
    }
}
