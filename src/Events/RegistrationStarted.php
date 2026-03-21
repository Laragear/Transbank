<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\Response;

readonly class RegistrationStarted
{
    /**
     * Create a new Registration Started instance.
     */
    public function __construct(public ApiRequest $apiRequest, public Response $response)
    {
        //
    }
}
