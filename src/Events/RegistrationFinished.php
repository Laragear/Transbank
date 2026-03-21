<?php

namespace Laragear\Transbank\Events;

use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\Transaction;

readonly class RegistrationFinished
{
    /**
     * Create a new Registration Finished instance.
     */
    public function __construct(public ApiRequest $apiRequest, public Transaction $transaction)
    {
        //
    }
}
