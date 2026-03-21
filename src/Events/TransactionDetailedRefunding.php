<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;

readonly class TransactionDetailedRefunding
{
    /**
     * Create a new Oneclick Charging instance.
     */
    public function __construct(public ApiRequest $apiRequest)
    {
        //
    }
}
