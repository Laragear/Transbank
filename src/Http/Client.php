<?php

namespace Laragear\Transbank\Http;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use JetBrains\PhpStorm\ArrayShape;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Exceptions\ClientException;
use Laragear\Transbank\Exceptions\NetworkException;
use Laragear\Transbank\Exceptions\ServerException;
use Laragear\Transbank\Exceptions\UnknownException;
use Laragear\Transbank\Transbank;
use Throwable;
use function str_replace;

class Client
{
    /**
     * Current API Version to use on Transbank Servers.
     *
     * @var string
     */
    public const API_VERSION = 'v1.3';

    /**
     * Transbank API Key header name.
     *
     * @var string
     */
    public const HEADER_KEY = 'Tbk-Api-Key-Id';

    /**
     * Transbank API Shared Secret header name.
     *
     * @var string
     */
    public const HEADER_SECRET = 'Tbk-Api-Key-Secret';

    /**
     * Production endpoint server.
     *
     * @var string
     */
    public const PRODUCTION_ENDPOINT = 'https://webpay3g.transbank.cl/';

    /**
     * Integration endpoint server.
     *
     * @var string
     */
    public const INTEGRATION_ENDPOINT = 'https://webpay3gint.transbank.cl/';

    /**
     * Create a new HTTP Client instance.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Http\Client\Factory  $http
     */
    public function __construct(protected ConfigContract $config, protected HttpFactory $http)
    {
        //
    }

    /**
     * Sends a transaction to Transbank servers.
     *
     * @param  string  $method
     * @param  string  $endpoint
     * @param  \Laragear\Transbank\ApiRequest  $request
     * @return \Illuminate\Http\Client\Response
     */
    public function send(string $method, string $endpoint, ApiRequest $request): Response
    {
        $pendingRequest = $this->http
            ->withoutRedirecting()
            ->retry($this->config->get('transbank.http.retries'))
            ->timeout($this->config->get('transbank.http.timeout'))
            ->withHeaders($this->getHeadersKeysForService($request->service))
            ->baseUrl($this->getTransbankBaseEndpoint())
            ->withUserAgent('php:laragear/transbank/'.Transbank::VERSION)
            ->withOptions($this->config->get('transbank.http.options'));

        $response = $this->toTransbank($pendingRequest, $request, $method, $endpoint);

        $this->throwExceptionOnResponseError($request, $response);

        return $response;
    }

    /**
     * Returns the headers for the service with its key and secret.
     *
     * @param  string  $service
     * @return array
     */
    #[ArrayShape([self::HEADER_KEY => 'string', self::HEADER_SECRET => 'string'])]
    protected function getHeadersKeysForService(string $service): array
    {
        return [
            static::HEADER_KEY => $this->config->get("transbank.credentials.$service.key"),
            static::HEADER_SECRET => $this->config->get("transbank.credentials.$service.secret"),
        ];
    }

    /**
     * Sends the request to Transbank servers.
     *
     * @param  \Illuminate\Http\Client\PendingRequest  $request
     * @param  \Laragear\Transbank\ApiRequest  $api
     * @param  string  $method
     * @param  string  $endpoint
     * @return \Illuminate\Http\Client\Response
     */
    protected function toTransbank(PendingRequest $request, ApiRequest $api, string $method, string $endpoint): Response
    {
        // If the request is reading, we won't send any data, or the request may stall.
        $data = $method === 'get' ? [] : ['json' => $api->attributes];

        try {
            return $request->send($method, $this->setApiVersion($endpoint), $data);
        } catch (ConnectionException $exception) {
            throw new NetworkException('Could not establish connection with Transbank.', $api, null, $exception);
        } catch (Throwable $exception) {
            throw new UnknownException('An error occurred when communicating with Transbank.', $api, null, $exception);
        }
    }

    /**
     * Replace the API Version from the endpoint.
     *
     * @param  string  $endpoint
     *
     * @return string
     */
    protected function setApiVersion(string $endpoint): string
    {
        return str_replace('{api_version}', static::API_VERSION, $endpoint);
    }

    /**
     * Checks if the Response is an error or not.
     *
     * @param  \Laragear\Transbank\ApiRequest  $apiRequest
     * @param  \Illuminate\Http\Client\Response  $response
     * @return void
     */
    protected function throwExceptionOnResponseError(ApiRequest $apiRequest, Response $response): void
    {
        // Bail out if the response is present but is not JSON.
        if ($response->header('Content-Type') !== 'application/json' ||
            !$response->toPsrResponse()->getBody()->getSize()) {
            throw new ServerException('Non-JSON response received.', $apiRequest, $response);
        }

        if ($response->redirect()) {
            throw new ServerException('A redirection was returned.', $apiRequest, $response);
        }

        if ($response->serverError()) {
            throw new ServerException($this->getErrorMessage($response), $apiRequest, $response);
        }

        if ($response->clientError()) {
            throw new ClientException($this->getErrorMessage($response), $apiRequest, $response);
        }
    }

    /**
     * Returns the error message from the Transbank response.
     *
     * @param  \Illuminate\Http\Client\Response  $response
     * @return string
     */
    protected function getErrorMessage(Response $response): string
    {
        return $response->json('error_message') ?? $response->body();
    }

    /**
     * Return the string used to reach Transbank servers.
     *
     * @return string
     */
    protected function getTransbankBaseEndpoint(): string
    {
        return $this->config->get('transbank.environment') === 'production'
            ? static::PRODUCTION_ENDPOINT
            : static::INTEGRATION_ENDPOINT;
    }
}
