<?php

namespace Laragear\Transbank\Exceptions;

use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;
use Throwable;

trait HandlesException
{
    /**
     * Transbank Exception constructor.
     */
    public function __construct(
        string $message = '',
        protected ?ApiRequest $apiRequest = null,
        protected ?Response $response = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, static::LOG_LEVEL, $previous);
    }

    /**
     * Returns the ApiRequest of this exception, if any.
     */
    public function getApiRequest(): ?ApiRequest
    {
        return $this->apiRequest;
    }

    /**
     * Returns the Response from Transbank, if any.
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
