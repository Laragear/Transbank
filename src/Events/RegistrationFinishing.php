<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

readonly class RegistrationFinishing
{
    /**
     * Create a new Registration Started instance.
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
