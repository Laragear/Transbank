<?php

namespace Laragear\Transbank\Exceptions;

use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;
use Throwable;

interface TransbankException extends Throwable
{
    /**
     * Returns the ApiRequest of this exception, if any.
     *
     * @return \Laragear\Transbank\ApiRequest|null
     */
    public function getApiRequest(): ?ApiRequest;

    /**
     * Returns the Response from Transbank, if any.
     *
     * @return \Illuminate\Http\Client\Response|null
     */
    public function getResponse(): ?Response;
}
