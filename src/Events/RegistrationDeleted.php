<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

readonly class RegistrationDeleted
{
    /**
     * Create a new Registration Deleting instance.
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
