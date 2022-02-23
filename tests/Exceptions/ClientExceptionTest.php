<?php

namespace Tests\Exceptions;

use Exception;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Exceptions\ClientException;
use Laragear\Transbank\Exceptions\NetworkException;
use Laragear\Transbank\Exceptions\ServerException;
use Laragear\Transbank\Exceptions\TransbankException;
use Laragear\Transbank\Exceptions\UnknownException;
use PHPUnit\Framework\TestCase;

class ClientExceptionTest extends TestCase
{
    public function test_client_exception_has_api_request_message_and_response(): void
    {
        $exception = new ClientException(
            'foo',
            $apiRequest = new ApiRequest('foo', 'bar', ['foo' => 'bar']),
            $response = new Response(new GuzzleResponse()),
            $previous = new Exception('previous')
        );

        static::assertEquals($apiRequest, $exception->getApiRequest());
        static::assertEquals($response, $exception->getResponse());
        static::assertEquals($previous, $exception->getPrevious());

        static::assertInstanceOf(TransbankException::class, $exception);
    }

    public function test_network_exception_has_api_request_message_and_response(): void
    {
        $exception = new NetworkException(
            'foo',
            $apiRequest = new ApiRequest('foo', 'bar', ['foo' => 'bar']),
            $response = new Response(new GuzzleResponse()),
            $previous = new Exception('previous')
        );

        static::assertEquals($apiRequest, $exception->getApiRequest());
        static::assertEquals($response, $exception->getResponse());
        static::assertEquals($previous, $exception->getPrevious());

        static::assertInstanceOf(TransbankException::class, $exception);
    }

    public function test_server_exception_has_api_request_message_and_response(): void
    {
        $exception = new ServerException(
            'foo',
            $apiRequest = new ApiRequest('foo', 'bar', ['foo' => 'bar']),
            $response = new Response(new GuzzleResponse()),
            $previous = new Exception('previous')
        );

        static::assertEquals($apiRequest, $exception->getApiRequest());
        static::assertEquals($response, $exception->getResponse());
        static::assertEquals($previous, $exception->getPrevious());

        static::assertInstanceOf(TransbankException::class, $exception);
    }

    public function test_unknown_exception_has_api_request_message_and_response(): void
    {
        $exception = new UnknownException(
            'foo',
            $apiRequest = new ApiRequest('foo', 'bar', ['foo' => 'bar']),
            $response = new Response(new GuzzleResponse()),
            $previous = new Exception('previous')
        );

        static::assertEquals($apiRequest, $exception->getApiRequest());
        static::assertEquals($response, $exception->getResponse());
        static::assertEquals($previous, $exception->getPrevious());

        static::assertInstanceOf(TransbankException::class, $exception);
    }
}
