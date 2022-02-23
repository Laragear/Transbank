<?php

namespace Laragear\Transbank\Services\Concerns;

use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;

trait DebugsTransactions
{
    /**
     * Debugs a given operation.
     *
     * @param  string  $message
     * @param  array  $context
     */
    protected function log(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Debugs a transaction before creating it.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     */
    protected function logCreating(ApiRequest $apiRequest): void
    {
        $this->logger->debug('Creating transaction', ['api_request' => $apiRequest]);
    }

    /**
     * Debugs a given operation.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     * @param  \Illuminate\Http\Client\Response  $response
     * @param  string|null  $token
     */
    protected function logResponse(ApiRequest $apiRequest, Response $response, string $token = null): void
    {
        $context = ['api_request' => $apiRequest, 'raw_response' => $response->json()];

        if ($token) {
            $context['token'] = $token;
        }

        $this->logger->debug('Response received', $context);
    }
}
