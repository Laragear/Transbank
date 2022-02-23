<?php

namespace Tests\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Events\TransactionCompleted;
use Laragear\Transbank\Events\TransactionCreated;
use Laragear\Transbank\Events\TransactionCreating;
use Laragear\Transbank\Http\Client;
use Laragear\Transbank\Services\Transactions\Response as TransbankResponse;
use Laragear\Transbank\Services\Transactions\Transaction;
use Laragear\Transbank\Services\Webpay;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use function ucfirst;

class WebpayTest extends TestCase
{
    use CreatesServerResponse;

    public function test_create(): void
    {
        $buyOrder = 'test-buyOrder';
        $amount = 100;
        $returnUrl = 'http://app.com/return';
        $sessionId = 'no-session-id';
        $token = '01ab1cc073c91fe5fc08a1b3b00ac3f63033a0e3dbdfdb1fde55c044ed8161b6';
        $url = 'https://webpay3g.transbank.cl/webpayserver/initTransaction';

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function($method, $endpoint, $request) use ($sessionId, $returnUrl, $amount, $buyOrder): bool {
                    static::assertSame('post', $method);
                    static::assertSame('rswebpaytransaction/api/webpay/{api_version}/transactions', $endpoint);
                    static::assertEquals(
                        new ApiRequest('webpay', 'create', [
                            'buy_order' => $buyOrder,
                            'amount' => $amount,
                            'session_id' => $sessionId,
                            'return_url' => $returnUrl,
                        ]),
                        $request
                    );

                    return true;
                }
            )
            ->andReturn($this->serverResponse(['token' => $token, 'url' => $url]));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($buyOrder, $amount, $returnUrl, $sessionId): bool {
                static::assertEquals('Creating transaction', $action);
                static::assertEquals($buyOrder, $context['api_request']['buy_order']);
                static::assertEquals($amount, $context['api_request']['amount']);
                static::assertEquals($returnUrl, $context['api_request']['return_url']);
                static::assertEquals($sessionId, $context['api_request']['session_id']);

                return true;
            }
        );

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($buyOrder, $amount, $returnUrl, $sessionId, $token, $url): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($buyOrder, $context['api_request']['buy_order']);
                static::assertEquals($amount, $context['api_request']['amount']);
                static::assertEquals($returnUrl, $context['api_request']['return_url']);
                static::assertEquals($sessionId, $context['api_request']['session_id']);
                static::assertEquals($token, $context['raw_response']['token']);
                static::assertEquals($url, $context['raw_response']['url']);

                return true;
            }
        );

        $response = $this->app->make(Webpay::class)->create($buyOrder, $amount, $returnUrl, $sessionId, []);

        static::assertEquals($response->getToken(), $token);
        static::assertEquals($response->getUrl(), $url);

        $event->assertDispatched(
            TransactionCreating::class,
            static function (TransactionCreating $event) use ($buyOrder, $amount, $returnUrl, $sessionId): bool {
                static::assertEquals('webpay', $event->apiRequest->service);
                static::assertEquals('create', $event->apiRequest->action);
                static::assertEquals($buyOrder, $event->apiRequest['buy_order']);
                static::assertEquals($amount, $event->apiRequest['amount']);
                static::assertEquals($returnUrl, $event->apiRequest['return_url']);
                static::assertEquals($sessionId, $event->apiRequest['session_id']);

                return true;
            }
        );

        $event->assertDispatched(
            TransactionCreated::class,
            static function (TransactionCreated $event) use ($buyOrder, $amount, $returnUrl, $sessionId, $token): bool {
                static::assertEquals('webpay', $event->apiRequest->service);
                static::assertEquals('create', $event->apiRequest->action);
                static::assertEquals($buyOrder, $event->apiRequest['buy_order']);
                static::assertEquals($amount, $event->apiRequest['amount']);
                static::assertEquals($returnUrl, $event->apiRequest['return_url']);
                static::assertEquals($sessionId, $event->apiRequest['session_id']);
                static::assertEquals(
                    new TransbankResponse($token, 'https://webpay3g.transbank.cl/webpayserver/initTransaction'),
                    $event->response
                );
                return true;
            }
        );
    }

    public function test_commit(): void
    {
        $token = '01abd1b55849b31783b352ebcb6adaf1f7d0dab7476aac499568c01585c5e289';

        $transbankResponse = [
            'vci' => 'TSY',
            'amount' => 10000,
            'status' => 'AUTHORIZED',
            'buy_order' => 'test_buy_order',
            'session_id' => 'test_session',
            'card_detail' => [
                'card_number' => '6623',
            ],
            'accounting_date' => '0324',
            'transaction_date' => '2021-01-24T22:16:48.562Z',
            'authorization_code' => '1213',
            'payment_type_code' => 'VN',
            'response_code' => 0,
            'installments_number' => 0,
        ];

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function($method, $endpoint, $request) use ($token): bool {
                    static::assertSame('put', $method);
                    static::assertSame("rswebpaytransaction/api/webpay/{api_version}/transactions/$token", $endpoint);
                    static::assertEquals(new ApiRequest('webpay', 'commit'), $request);

                    return true;
                }
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($token): bool {
                static::assertEquals('Committing transaction', $action);
                static::assertEquals($token, $context['token']);
                static::assertEquals('webpay', $context['api_request']->service);
                static::assertEquals('commit', $context['api_request']->action);

                return true;
            }
        )->andReturnNull();

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($transbankResponse, $token): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($token, $context['token']);
                static::assertEquals('webpay', $context['api_request']->service);
                static::assertEquals('commit', $context['api_request']->action);
                static::assertEquals($transbankResponse, $context['raw_response']);

                return true;
            }
        );

        $response = $this->app->make(Webpay::class)->commit($token);

        static::assertEquals('webpay', $response->service);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionCompleted::class,
            static function (TransactionCompleted $event) use ($transbankResponse): bool {
                static::assertEquals('webpay', $event->apiRequest->service);
                static::assertEquals('commit', $event->apiRequest->action);
                static::assertEquals(new Transaction('webpay', 'commit', $transbankResponse), $event->transaction);

                return true;
            }
        );
    }

    public function test_status(): void
    {
        $token = '01abd1b55849b31783b352ebcb6adaf1f7d0dab7476aac499568c01585c5e289';

        $transbankResponse = [
            'vci' => 'TSY',
            'amount' => 10000,
            'status' => 'INITIALIZED',
            'buy_order' => 'test_buy_order',
            'session_id' => 'test_session',
            'card_detail' => [
                'card_number' => '6623',
            ],
            'accounting_date' => '0324',
            'transaction_date' => '2021-01-24T22:16:48.562Z',
            'payment_type_code' => 'VN',
            'installments_number' => 0,
        ];

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function($method, $endpoint, $request) use ($token): bool {
                    static::assertSame('get', $method);
                    static::assertSame("rswebpaytransaction/api/webpay/{api_version}/transactions/$token", $endpoint);
                    static::assertEquals(new ApiRequest('webpay', 'status'), $request);

                    return true;
                }
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($token): bool {
                static::assertEquals('Retrieving transaction status', $action);
                static::assertEquals($token, $context['token']);
                static::assertEquals('webpay', $context['api_request']->service);
                static::assertEquals('status', $context['api_request']->action);

                return true;
            }
        );

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($token, $transbankResponse): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($token, $context['token']);
                static::assertEquals('webpay', $context['api_request']->service);
                static::assertEquals('status', $context['api_request']->action);
                static::assertEquals($transbankResponse, $context['raw_response']);

                return true;
            }
        );

        $response = $this->app->make(Webpay::class)->status($token);

        static::assertEquals('webpay', $response->service);
        static::assertEquals('status', $response->action);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertNothingDispatched();
    }

    public function test_refund(): void
    {
        $token = '01abd1b55849b31783b352ebcb6adaf1f7d0dab7476aac499568c01585c5e289';

        $transbankResponse = [
            'type' => 'NULLIFIED',
            'authorization_code' => '123456',
            'authorization_date' => '2019-03-20T20:18:20Z',
            'nullified_amount' => $nullifiedAmount = 1000.00,
            'balance' => 0.00,
            'response_code' => 0,
        ];

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')
            ->withArgs(
                static function (string $action, array $context) use ($nullifiedAmount, $token): bool {
                    static::assertEquals('Refunding transaction', $action);
                    static::assertEquals($token, $context['token']);
                    static::assertEquals('webpay', $context['api_request']->service);
                    static::assertEquals('refund', $context['api_request']->action);
                    static::assertEquals($nullifiedAmount, $context['api_request']['amount']);

                    return true;
                }
        );

        $logger->expects('debug')
            ->withArgs(
                static function ($action, $context) use ($transbankResponse, $nullifiedAmount, $token): bool {
                    static::assertEquals('Response received', $action);
                    static::assertEquals($token, $context['token']);
                    static::assertEquals('webpay', $context['api_request']->service);
                    static::assertEquals('refund', $context['api_request']->action);
                    static::assertEquals($nullifiedAmount, $context['api_request']['amount']);
                    static::assertEquals($transbankResponse, $context['raw_response']);

                    return true;
                }
        )->andReturnNull();

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function($method, $endpoint, $request) use ($nullifiedAmount, $token): bool {
                    static::assertSame('put', $method);
                    static::assertSame(
                        "rswebpaytransaction/api/webpay/{api_version}/transactions/$token/refunds", $endpoint
                    );
                    static::assertEquals(new ApiRequest('webpay', 'refund', ['amount' => $nullifiedAmount]), $request);

                    return true;
                }
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $response = $this->app->make(Webpay::class)->refund($token, $nullifiedAmount);

        static::assertEquals($response->getNullifiedAmount(), $nullifiedAmount);
        static::assertTrue($response->isSuccessful());

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionCreating::class,
            static function (TransactionCreating $event) use ($nullifiedAmount): bool {
                static::assertEquals('webpay', $event->apiRequest->service);
                static::assertEquals('refund', $event->apiRequest->action);
                static::assertEquals($event->apiRequest['amount'], $nullifiedAmount);

                return true;
            }
        );

        $event->assertDispatched(
            TransactionCompleted::class,
            static function (TransactionCompleted $event) use ($transbankResponse, $nullifiedAmount): bool {
                static::assertEquals('webpay', $event->apiRequest->service);
                static::assertEquals('refund', $event->apiRequest->action);
                static::assertEquals($event->apiRequest['amount'], $nullifiedAmount);
                static::assertEquals(new Transaction('webpay', 'refund', $transbankResponse), $event->transaction);

                return true;
            }
        );
    }

    public function test_capture(): void
    {
        $buyOrder = 'test_buy_order';
        $code = 123456;
        $amount = 1000;
        $token = 'e074d38c628122c63e5c0986368ece22974d6fee1440617d85873b7b4efa48a3';

        $transbankResponse = [
            'token' => $token,
            'authorization_code' => $code,
            'authorization_date' => '2019-03-20T20:18:20Z',
            'captured_amount' => $amount,
            'response_code' => 0,
        ];

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(function (string $action, array $context) use ($token) {
            static::assertEquals('Capturing transaction', $action);
            static::assertEquals($token, $context['token']);

            return true;
        })->andReturnNull();

        $logger->expects('debug')->withArgs(function (string $action, array $context) use (
            $transbankResponse,
            $token
        ) {
            static::assertEquals('Response received', $action);
            static::assertEquals($token, $context['token']);
            static::assertEquals($transbankResponse, $context['raw_response']);

            return true;
        })->andReturnNull();

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function($method, $endpoint, $request) use ($buyOrder, $amount, $code, $token): bool {
                    static::assertSame('put', $method);
                    static::assertSame(
                        "rswebpaytransaction/api/webpay/{api_version}/transactions/$token/capture", $endpoint
                    );
                    static::assertEquals(
                        new ApiRequest('webpay', 'capture', [
                            'authorization_code' => $code,
                            'buy_order' => $buyOrder,
                            'capture_amount' => $amount,
                        ]),
                        $request
                    );

                    return true;
                }
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $response = $this->app->make(Webpay::class)->capture($token, $buyOrder, $code, $amount);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionCompleted::class,
            static function (TransactionCompleted $event) use ($amount, $code, $buyOrder, $transbankResponse) {
                static::assertEquals('webpay', $event->apiRequest->service);
                static::assertEquals('capture', $event->apiRequest->action);
                static::assertEquals($event->apiRequest['buy_order'], $buyOrder);
                static::assertEquals($event->apiRequest['authorization_code'], $code);
                static::assertEquals($event->apiRequest['capture_amount'], $amount);
                static::assertEquals(new Transaction('webpay', 'capture', $transbankResponse), $event->transaction);

                return true;
            }
        );
    }
}
