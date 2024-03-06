<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

class TransactionCreating
{
    /**
     * Create a new Transaction Creating event.
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
