<?php

namespace Laragear\Transbank\Services\Concerns;

use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Services\Transactions\Transaction;
use function array_keys;
use function str_replace;

trait SendsRequests
{
    /**
     * Creates a new API Request.
     *
     * @param  string  $action
     * @param  array  $attributes
     * @return \Laragear\Transbank\ApiRequest
     */
    #[\JetBrains\PhpStorm\Pure]
    protected function request(string $action, array $attributes = []): ApiRequest
    {
        return new ApiRequest(static::SERVICE_NAME, $action, $attributes);
    }

    /**
     * Sends a ApiRequest to Transbank, returns a response array.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     * @param  array  $replace
     * @return \Illuminate\Http\Client\Response
     */
    protected function send(ApiRequest $apiRequest, array $replace = []): Response
    {
        [$method, $endpoint] = static::ENDPOINTS[$apiRequest->action];

        return $this->client->send(
            $method, str_replace(array_keys($replace), $replace, $endpoint), $apiRequest
        );
    }

    /**
     * Returns the Transaction object from Transbank response.
     *
     * @param  string  $action
     * @param  \Illuminate\Http\Client\Response  $response
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     */
    protected function transaction(string $action, Response $response): Transaction
    {
        return new Transaction(static::SERVICE_NAME, $action, $response->json());
    }
}
