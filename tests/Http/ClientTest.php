<?php

namespace Tests\Http;

use Exception;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Exceptions\ClientException;
use Laragear\Transbank\Exceptions\NetworkException;
use Laragear\Transbank\Exceptions\ServerException;
use Laragear\Transbank\Exceptions\UnknownException;
use Laragear\Transbank\Http\Client;
use Laragear\Transbank\Services\Webpay;
use Laragear\Transbank\Transbank;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use function json_encode;

class ClientTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app->make('config')->set([
            'transbank.credentials.foo.key' => 'key',
            'transbank.credentials.foo.secret' => 'secret',
        ]);
    }

    protected static function response(int $code = 200): Response
    {
        return new Response(
            new GuzzleResponse(
                $code, ['content-type' => 'application/json'], json_encode(['baz' => 'quz'])
            )
        );
    }

    public function test_sends_properly_configured_request(): void
    {
        $this->mock(Factory::class)
            ->expects('withoutRedirecting')
            ->andReturnUsing(
                static function (): MockInterface {
                    $mock = Mockery::mock(PendingRequest::class);

                    $mock->expects('retry')->with(3)->andReturnSelf();
                    $mock->expects('timeout')->with(10)->andReturnSelf();
                    $mock->expects('withHeaders')
                        ->with([Client::HEADER_KEY => 'key', Client::HEADER_SECRET => 'secret'])
                        ->andReturnSelf();
                    $mock->expects('baseUrl')->with(Client::INTEGRATION_ENDPOINT)->andReturnSelf();
                    $mock->expects('withUserAgent')->with('php:laragear/transbank/'.Transbank::VERSION)->andReturnSelf();
                    $mock->expects('withOptions')
                        ->with(['synchronous' => true])
                        ->andReturnSelf();

                    $mock->expects('send')->with('post', 'https://endpoint/v1.2/', ['json' => ['foo' => 'bar']])->andReturn(static::response());

                    return $mock;
                }
            );

        $response = $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );

        static::assertInstanceOf(Response::class, $response);
        static::assertSame('quz', $response->json('baz'));
    }

    public function test_uses_custom_retries(): void
    {
        $this->app->make('config')->set('transbank.http.retries', 10);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('retry')->with(10)->andReturnSelf();
                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_uses_custom_timeout(): void
    {
        $this->app->make('config')->set('transbank.http.timeout', 88);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('timeout')->with(88)->andReturnSelf();
                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_sets_headers_with_webpay_service_keys(): void
    {
        $this->app->make('config')->set('transbank.http.timeout', 88);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('withHeaders')
                    ->with([
                        Client::HEADER_KEY => Webpay::INTEGRATION_KEY,
                        Client::HEADER_SECRET => Transbank::INTEGRATION_SECRET,
                    ])
                    ->andReturnSelf();
                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('webpay', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_uses_integration_endpoint_on_not_production(): void
    {
        $this->app->make('config')->set('transbank.environment', 'anything-not-production');

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('baseUrl')->with(Client::INTEGRATION_ENDPOINT)->andReturnSelf();
                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_uses_production_endpoint_on_production(): void
    {
        $this->app->make('config')->set('transbank.environment', 'production');

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('baseUrl')->with(Client::PRODUCTION_ENDPOINT)->andReturnSelf();
                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_adds_config_options(): void
    {
        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('withOptions')
                    ->with(['foo' => 'bar'])
                    ->andReturnSelf();
                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_factory_returns_connection_exception_transformed_into_network_exception(): void
    {
        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Could not establish connection with Transbank.');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andThrow(ConnectionException::class);

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_factory_returns_throwable_transformed_into_unknown_exception(): void
    {
        $this->expectException(UnknownException::class);
        $this->expectExceptionMessage('An error occurred when communicating with Transbank.');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andThrow(Exception::class);

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_returns_successful_response(): void
    {
        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andReturn(static::response());

                return $pending;
            }
        );

        $response = $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );

        static::assertTrue($response->ok());
    }

    public function test_throws_server_exception_if_response_not_json(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Non-JSON response received');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andReturn(
                    new Response(new GuzzleResponse(200, [], json_encode(['baz' => 'quz'])))
                );

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_throws_server_exception_if_response_empty(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Non-JSON response received');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andReturn(new Response(new GuzzleResponse(200, [], '')));

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_throws_server_exception_if_response_redirection(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('A redirection was returned.');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andReturn(
                    new Response(
                        new GuzzleResponse(
                            301, ['content-type' => 'application/json'], json_encode(['baz' => 'quz'])
                        )
                    )
                );

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_throws_server_exception_if_response_server_error(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('test_error');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andReturn(
                    new Response(
                        new GuzzleResponse(
                            500, ['content-type' => 'application/json'], json_encode(['error_message' => 'test_error'])
                        )
                    )
                );

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_throws_client_exception_if_response_client_error(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('test_error');

        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->andReturn(
                    new Response(
                        new GuzzleResponse(
                            400, ['content-type' => 'application/json'], json_encode(['error_message' => 'test_error'])
                        )
                    )
                );

                return $pending;
            }
        );

        $this->app->make(Client::class)->send(
            'post', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );
    }

    public function test_doesnt_sends_api_data_when_method_is_read(): void
    {
        $this->app->make('config')->set('transbank.http.options', ['foo' => 'bar']);

        $this->mock(Factory::class)->expects('withoutRedirecting')->andReturnUsing(
            static function (): MockInterface {
                $pending = Mockery::mock(PendingRequest::class)->makePartial();

                $pending->expects('send')->with('get', 'https://endpoint/v1.2/', [])->andReturn(static::response());

                return $pending;
            }
        );

        $response = $this->app->make(Client::class)->send(
            'get', 'https://endpoint/{api_version}/', new ApiRequest('foo', 'bar', ['foo' => 'bar'])
        );

        static::assertTrue($response->ok());
    }
}

