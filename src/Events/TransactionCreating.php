<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

class TransactionCreating
{
    /**
     * Create a new Transaction Creating event.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
