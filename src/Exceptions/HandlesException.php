<?php

namespace Laragear\Transbank\Exceptions;

use Illuminate\Http\Client\Response;
use JetBrains\PhpStorm\Pure;
use Laragear\Transbank\ApiRequest;
use Throwable;

trait HandlesException
{
    /**
     * Transbank Exception constructor.
     *
     * @param  string  $message
     * @param  \Laragear\Transbank\ApiRequest|null  $apiRequest
     * @param  \Illuminate\Http\Client\Response|null  $response
     * @param  Throwable|null  $previous
     */
    #[Pure]
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
     *
     * @return \Laragear\Transbank\ApiRequest|null
     */
    public function getApiRequest(): ?ApiRequest
    {
        return $this->apiRequest;
    }

    /**
     * Returns the Response from Transbank, if any.
     *
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
