<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\Response;

class TransactionCreated
{
    /**
     * Create a new Transaction Created event.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest  Data sent to Transbank.
     * @param  \Laragear\Transbank\Services\Transactions\Response  $response  Raw response from Transbank.
     */
    public function __construct(public ApiRequest $apiRequest, public Response $response)
    {
        //
    }
}
