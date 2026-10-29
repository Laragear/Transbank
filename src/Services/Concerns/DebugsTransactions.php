<?php

namespace Laragear\Transbank\Services\Concerns;

use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;

use function substr;

trait DebugsTransactions
{
    /**
     * Debugs a given operation.
     */
    protected function log(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * Debugs a transaction before creating it.
     */
    protected function logCreating(ApiRequest $apiRequest): void
    {
        $this->logger->debug('Creating transaction', ['api_request' => $apiRequest]);
    }

    /**
     * Debugs a given operation.
     */
    protected function logResponse(ApiRequest $apiRequest, Response $response, ?string $token = null): void
    {
        $context = ['api_request' => $apiRequest, 'raw_response' => $response->json()];

        if ($token) {
            $context['token'] = $token;
        }

        $this->logger->debug('Response received', $context);
    }

    /**
     * Masks a `tbk_user` credential for logging.
     *
     * The `tbk_user` authorizes charges on a stored card, so it must never be
     * logged raw. The prefix is kept for support correlation.
     */
    protected function maskTbkUser(string $tbkUser): string
    {
        return substr($tbkUser, 0, 8).'...';
    }

    /**
     * Returns a copy of the API Request with the `tbk_user` credential masked for logging.
     *
     * The original request is left untouched, so it can still be sent to Transbank raw.
     */
    protected function redactApiRequest(ApiRequest $apiRequest): ApiRequest
    {
        if (! isset($apiRequest['tbk_user'])) {
            return $apiRequest;
        }

        $redacted = clone $apiRequest;

        $redacted['tbk_user'] = $this->maskTbkUser($redacted['tbk_user']);

        return $redacted;
    }
}
