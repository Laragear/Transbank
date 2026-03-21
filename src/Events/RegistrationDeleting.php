<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

readonly class RegistrationDeleting
{
    /**
     * Create a new Registration Deleting instance.
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
