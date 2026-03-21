<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

readonly class TransactionDetailedCharging
{
    /**
     * Create a new Oneclick Charging instance.
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
