<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\Response;

class TransactionCreated
{
    /**
     * Create a new Transaction Created event.
     */
    public function __construct(public ApiRequest $apiRequest, public Response $response)
    {
        //
    }
}
