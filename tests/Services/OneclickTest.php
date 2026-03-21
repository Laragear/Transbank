<?php

namespace Tests\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Events\RegistrationDeleted;
use Laragear\Transbank\Events\RegistrationDeleting;
use Laragear\Transbank\Events\RegistrationFinished;
use Laragear\Transbank\Events\RegistrationFinishing;
use Laragear\Transbank\Events\RegistrationStarted;
use Laragear\Transbank\Events\RegistrationStarting;
use Laragear\Transbank\Events\TransactionDetailedCaptured;
use Laragear\Transbank\Events\TransactionDetailedCapturing;
use Laragear\Transbank\Events\TransactionDetailedCharged;
use Laragear\Transbank\Events\TransactionDetailedCharging;
use Laragear\Transbank\Events\TransactionDetailedRefunded;
use Laragear\Transbank\Events\TransactionDetailedRefunding;
use Laragear\Transbank\Events\TransactionDetailedRetrieved;
use Laragear\Transbank\Events\TransactionDetailedRetrieving;
use Laragear\Transbank\Http\Client;
use Laragear\Transbank\Services\Oneclick;
use Laragear\Transbank\Services\Transactions\Response as TransbankResponse;
use Laragear\Transbank\Services\Transactions\Transaction;
use Laragear\Transbank\Services\Transactions\TransactionDetailed;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use function ucfirst;

class OneclickTest extends TestCase
{
    use CreatesServerResponse;

    public function test_registers(): void
    {
        $username = 'test-username';
        $email = 'test@email.com';
        $returnUrl = 'http://app.com/return';
        $token = '01ab1cc073c91fe5fc08a1b3b00ac3f63033a0e3dbdfdb1fde55c044ed8161b6';
        $url = 'https://webpay3g.transbank.cl/webpayserver/bp_inscription.cgi';

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($returnUrl, $email, $username): bool {
                    static::assertSame('post', $method);
                    static::assertSame('rswebpaytransaction/api/oneclick/{api_version}/inscriptions', $endpoint);
                    static::assertEquals(
                        new ApiRequest('oneclick', 'register', [
                            'username' => $username,
                            'email' => $email,
                            'response_url' => $returnUrl,
                        ]),
                        $request,
                    );

                    return true;
                },
            )
            ->andReturn($this->serverResponse(['token' => $token, 'url_webpay' => $url]));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($username, $email, $returnUrl): bool {
                static::assertEquals('Creating registration', $action);
                static::assertEquals($username, $context['api_request']['username']);
                static::assertEquals($email, $context['api_request']['email']);
                static::assertEquals($returnUrl, $context['api_request']['response_url']);

                return true;
            },
        );

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($username, $email, $returnUrl, $token, $url): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($username, $context['api_request']['username']);
                static::assertEquals($email, $context['api_request']['email']);
                static::assertEquals($returnUrl, $context['api_request']['response_url']);
                static::assertEquals($token, $context['raw_response']['token']);
                static::assertEquals($url, $context['raw_response']['url_webpay']);

                return true;
            },
        );

        $response = $this->app->make(Oneclick::class)->register('test-username', 'test@email.com', $returnUrl);

        static::assertEquals($response->getToken(), $token);
        static::assertEquals($response->getUrl(), $url);

        $event->assertDispatched(
            RegistrationStarting::class,
            static function (RegistrationStarting $event) use ($username, $email, $returnUrl): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('register', $event->apiRequest->action);
                static::assertEquals($username, $event->apiRequest['username']);
                static::assertEquals($email, $event->apiRequest['email']);
                static::assertEquals($returnUrl, $event->apiRequest['response_url']);

                return true;
            },
        );

        $event->assertDispatched(
            RegistrationStarted::class,
            static function (RegistrationStarted $event) use ($username, $email, $returnUrl, $token): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('register', $event->apiRequest->action);
                static::assertEquals($username, $event->apiRequest['username']);
                static::assertEquals($email, $event->apiRequest['email']);
                static::assertEquals($returnUrl, $event->apiRequest['response_url']);

                static::assertEquals(
                    new TransbankResponse($token, 'https://webpay3g.transbank.cl/webpayserver/bp_inscription.cgi',
                        'TBK_TOKEN'),
                    $event->response,
                );
                return true;
            },
        );
    }

    public function test_confirm(): void
    {
        $token = '01abd1b55849b31783b352ebcb6adaf1f7d0dab7476aac499568c01585c5e289';

        $transbankResponse = [
            'response_code' => 0,
            'tbk_user' => 'b6bd6ba3-e718-4107-9386-d2b099a8dd42',
            'authorization_code' => '123456',
            'card_type' => 'Visa',
            'card_number' => 'XXXXXXXXXXXX6623',
        ];

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($token): bool {
                    static::assertSame('put', $method);
                    static::assertSame("rswebpaytransaction/api/oneclick/{api_version}/inscriptions/$token", $endpoint);
                    static::assertEquals(new ApiRequest('oneclick', 'confirm'), $request);

                    return true;
                },
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($token): bool {
                static::assertEquals('Finishing registration', $action);
                static::assertEquals($token, $context['tbkUser']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('confirm', $context['api_request']->action);

                return true;
            },
        )->andReturnNull();

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($token, $transbankResponse): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($token, $context['token']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('confirm', $context['api_request']->action);
                static::assertEquals($transbankResponse, $context['raw_response']);

                return true;
            },
        )->andReturnNull();

        $response = $this->app->make(Oneclick::class)->confirm($token);

        static::assertEquals('oneclick', $response->service);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            RegistrationFinishing::class,
            static function (RegistrationFinishing $event) use ($transbankResponse): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('confirm', $event->apiRequest->action);

                return true;
            },
        );

        $event->assertDispatched(
            RegistrationFinished::class,
            static function (RegistrationFinished $event) use ($transbankResponse): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('confirm', $event->apiRequest->action);
                static::assertEquals(new Transaction('oneclick', 'confirm', $transbankResponse), $event->transaction);

                return true;
            },
        );
    }

    public function test_delete(): void
    {
        $tbkUser = 'b6bd6ba3-e718-4107-9386-d2b099a8dd42';
        $username = 'juanperez';

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($tbkUser, $username): bool {
                    static::assertSame('delete', $method);
                    static::assertSame('rswebpaytransaction/api/oneclick/{api_version}/inscriptions', $endpoint);
                    static::assertEquals(
                        new ApiRequest('oneclick', 'delete', [
                            'tbk_user' => $tbkUser,
                            'username' => $username,
                        ]),
                        $request,
                    );

                    return true;
                },
            )
            ->andReturn($this->serverResponse([], 204));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($tbkUser, $username): bool {
                static::assertEquals('Deleting registration', $action);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('delete', $context['api_request']->action);
                static::assertEquals($tbkUser, $context['api_request']['tbk_user']);
                static::assertEquals($username, $context['api_request']['username']);

                return true;
            },
        )->andReturnNull();

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($tbkUser, $username): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('delete', $context['api_request']->action);
                static::assertEmpty($context['raw_response']);

                return true;
            },
        )->andReturnNull();

        $this->app->make(Oneclick::class)->delete($tbkUser, $username);

        $event->assertDispatched(
            RegistrationDeleting::class,
            static function (RegistrationDeleting $event) use ($tbkUser, $username): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('delete', $event->apiRequest->action);
                static::assertEquals($tbkUser, $event->apiRequest['tbk_user']);
                static::assertEquals($username, $event->apiRequest['username']);

                return true;
            },
        );

        $event->assertDispatched(
            RegistrationDeleted::class,
            static function (RegistrationDeleted $event) use ($tbkUser, $username): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('delete', $event->apiRequest->action);
                static::assertEquals($tbkUser, $event->apiRequest['tbk_user']);
                static::assertEquals($username, $event->apiRequest['username']);

                return true;
            },
        );
    }

    public function test_commit(): void
    {
        $tbkUser = 'b6bd6ba3-e718-4107-9386-d2b099a8dd42';
        $username = 'juanperez';
        $buyOrder = 'test_buy_order';
        $details = [
            [
                'commerce_code' => Oneclick::INTEGRATION_CHILD_KEY,
                'buy_order' => $childBuyOrder = "child-$buyOrder",
                'amount' => 1000,
                'installments_number' => 1,
            ]
        ];

        $transbankResponse = [
            'buy_order' => $buyOrder,
            'card_detail' => [
                'card_number' => '6623',
            ],
            'accounting_date' => '0324',
            'transaction_date' => '2021-01-24T22:16:48.562Z',
            'details' => [
                [
                    'amount' => 10000,
                    'status' => 'AUTHORIZED',
                    'authorization_code' => '1213',
                    'payment_type_code' => 'VN',
                    'response_code' => 0,
                    'installments_number' => 0,
                    'commerce_code' => Oneclick::INTEGRATION_CHILD_KEY,
                    'buy_order' => $childBuyOrder,
                ]
            ],
        ];

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($buyOrder, $tbkUser, $username, $details): bool {
                    static::assertSame('post', $method);
                    static::assertSame('rswebpaytransaction/api/oneclick/{api_version}/transactions', $endpoint);
                    static::assertEquals(new ApiRequest('oneclick', 'commit', [
                        'tbk_user' => $tbkUser,
                        'username' => $username,
                        'buy_order' => $buyOrder,
                        'details' => $details,
                    ]), $request);

                    return true;
                },
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($tbkUser): bool {
                static::assertEquals('Committing transaction', $action);
                static::assertEquals($tbkUser, $context['tbk_user']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('commit', $context['api_request']->action);

                return true;
            },
        )->andReturnNull();

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($transbankResponse, $tbkUser): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($tbkUser, $context['tbk_user']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('commit', $context['api_request']->action);
                static::assertEquals($transbankResponse, $context['raw_response']);

                return true;
            },
        );

        $response = $this->app->make(Oneclick::class)->commit($tbkUser, $username, $buyOrder, $details);

        static::assertEquals('oneclick', $response->service);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionDetailedCharging::class,
            static function (TransactionDetailedCharging $event) use ($transbankResponse): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('commit', $event->apiRequest->action);
                return true;
            },
        );

        $event->assertDispatched(
            TransactionDetailedCharged::class,
            static function (TransactionDetailedCharged $event) use ($transbankResponse): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('commit', $event->apiRequest->action);
                static::assertEquals(new TransactionDetailed('oneclick', 'commit', $transbankResponse), $event->transaction);

                return true;
            },
        );
    }

    public function test_status(): void
    {
        $buyOrder = 'test_buy_order';

        $transbankResponse = [
            'buy_order' => $buyOrder,
            'card_detail' => [
                'card_number' => '6623',
            ],
            'accounting_date' => '0324',
            'transaction_date' => '2021-01-24T22:16:48.562Z',
            'details' => [
                [
                    'amount' => 10000,
                    'status' => 'AUTHORIZED',
                    'authorization_code' => '1213',
                    'payment_type_code' => 'VN',
                    'response_code' => 0,
                    'installments_number' => 0,
                    'commerce_code' => Oneclick::INTEGRATION_CHILD_KEY,
                    'buy_order' => "child-$buyOrder",
                ]
            ],
        ];

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($buyOrder): bool {
                    static::assertSame('get', $method);
                    static::assertSame(
                        "rswebpaytransaction/api/oneclick/{api_version}/transactions/$buyOrder", $endpoint
                    );
                    static::assertEquals(new ApiRequest('oneclick', 'status'), $request);

                    return true;
                },
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($buyOrder): bool {
                static::assertEquals('Retrieving transaction status', $action);
                static::assertEquals($buyOrder, $context['buy_order']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('status', $context['api_request']->action);

                return true;
            },
        )->andReturnNull();

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($transbankResponse, $buyOrder): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($buyOrder, $context['buy_order']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('status', $context['api_request']->action);
                static::assertEquals($transbankResponse, $context['raw_response']);

                return true;
            },
        );

        $response = $this->app->make(Oneclick::class)->status($buyOrder);

        static::assertEquals('oneclick', $response->service);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionDetailedRetrieving::class,
            static function (TransactionDetailedRetrieving $event) use ($transbankResponse, $buyOrder): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('status', $event->apiRequest->action);
                static::assertEquals($buyOrder, $event->buyOrder);

                return true;
            },
        );

        $event->assertDispatched(
            TransactionDetailedRetrieved::class,
            static function (TransactionDetailedRetrieved $event) use ($transbankResponse): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('status', $event->apiRequest->action);
                static::assertEquals(
                    new TransactionDetailed('oneclick', 'status', $transbankResponse), $event->transaction
                );

                return true;
            },
        );
    }

    public function test_refund(): void
    {
        $buyOrder = 'test_buy_order';
        $detailBuyOrder = "child-$buyOrder";
        $commerceCode = Oneclick::INTEGRATION_CHILD_KEY;
        $amount = 1000;

        $transbankResponse = [
            'type' => 'REVERSED',
        ];

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($buyOrder, $commerceCode, $detailBuyOrder, $amount): bool {
                    static::assertSame('post', $method);
                    static::assertSame("rswebpaytransaction/api/oneclick/{api_version}/transactions/$buyOrder/refunds", $endpoint);
                    static::assertEquals(new ApiRequest('oneclick', 'refund', [
                        'buy_order' => $buyOrder,
                        'commerce_code' => $commerceCode,
                        'detail_buy_order' => $detailBuyOrder,
                        'amount' => $amount,
                    ]), $request);

                    return true;
                },
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($buyOrder): bool {
                static::assertEquals('Refunding transaction', $action);
                static::assertEquals($buyOrder, $context['buy_order']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('refund', $context['api_request']->action);

                return true;
            },
        )->andReturnNull();

        $logger->expects('debug')->withArgs(
            static function (string $action, array $context) use ($transbankResponse, $buyOrder): bool {
                static::assertEquals('Response received', $action);
                static::assertEquals($buyOrder, $context['buy_order']);
                static::assertEquals('oneclick', $context['api_request']->service);
                static::assertEquals('refund', $context['api_request']->action);
                static::assertEquals($transbankResponse, $context['raw_response']);

                return true;
            },
        );

        $response = $this->app->make(Oneclick::class)->refund($buyOrder, $commerceCode, $detailBuyOrder, $amount);

        static::assertEquals('oneclick', $response->service);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionDetailedRefunding::class,
            static function (TransactionDetailedRefunding $event) use ($amount, $detailBuyOrder, $commerceCode, $buyOrder): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('refund', $event->apiRequest->action);
                static::assertEquals([
                    'buy_order' => $buyOrder,
                    'commerce_code' => $commerceCode,
                    'detail_buy_order' => $detailBuyOrder,
                    'amount' => $amount,
                ], $event->apiRequest->attributes);

                return true;
            },
        );

        $event->assertDispatched(
            TransactionDetailedRefunded::class,
            static function (TransactionDetailedRefunded $event) use ($transbankResponse): bool {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('refund', $event->apiRequest->action);
                static::assertEquals(
                    new TransactionDetailed('oneclick', 'refund', $transbankResponse), $event->transaction
                );

                return true;
            },
        );
    }

    public function test_capture(): void
    {
        $buyOrder = 'test_buy_order';
        $commerceCode = Oneclick::INTEGRATION_CHILD_KEY;
        $amount = 1000;
        $authorizationCode = '1234';

        $transbankResponse = [
            'authorization_code' => $authorizationCode,
            'authorization_date' => '2020-04-03T01:49:50.181Z',
            'captured_amount' => 50,
            'response_code' => 0
        ];

        $event = Event::fake();

        $logger = $this->mock(LoggerInterface::class);

        $logger->expects('debug')->withArgs(function (string $action, array $context) {
            static::assertEquals('Capturing transaction', $action);
            static::assertEquals('oneclick', $context['api_request']->service);
            static::assertEquals('capture', $context['api_request']->action);

            return true;
        })->andReturnNull();

        $logger->expects('debug')->withArgs(function (string $action, array $context) use ($transbankResponse) {
            static::assertEquals('Response received', $action);
            static::assertEquals('oneclick', $context['api_request']->service);
            static::assertEquals('capture', $context['api_request']->action);
            static::assertEquals($transbankResponse, $context['raw_response']);

            return true;
        })->andReturnNull();

        $this->mock(Client::class)
            ->expects('send')
            ->withArgs(
                static function ($method, $endpoint, $request) use ($authorizationCode, $buyOrder, $commerceCode, $amount): bool {
                    static::assertSame('put', $method);
                    static::assertSame("rswebpaytransaction/api/oneclick/{api_version}/transactions/capture", $endpoint);
                    static::assertEquals(new ApiRequest('oneclick', 'capture', [
                        'commerce_code' => $commerceCode,
                        'buy_order' => $buyOrder,
                        'capture_amount' => $amount,
                        'authorization_code' => $authorizationCode,
                    ]), $request);

                    return true;
                },
            )
            ->andReturn($this->serverResponse($transbankResponse));

        $response = $this->app->make(Oneclick::class)->capture($buyOrder, $commerceCode, $authorizationCode, $amount);

        foreach ($transbankResponse as $key => $value) {
            static::assertEquals($value, $response->{'get'.ucfirst(Str::camel($key))}());
            static::assertEquals($value, $response->{$key});
        }

        $event->assertDispatched(
            TransactionDetailedCapturing::class,
            static function (TransactionDetailedCapturing $event) use ($amount, $authorizationCode, $buyOrder, $transbankResponse) {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('capture', $event->apiRequest->action);
                static::assertEquals($event->apiRequest['buy_order'], $buyOrder);
                static::assertEquals($event->apiRequest['authorization_code'], $authorizationCode);
                static::assertEquals($event->apiRequest['capture_amount'], $amount);

                return true;
            },
        );

        $event->assertDispatched(
            TransactionDetailedCaptured::class,
            static function (TransactionDetailedCaptured $event) use ($amount, $authorizationCode, $buyOrder, $transbankResponse) {
                static::assertEquals('oneclick', $event->apiRequest->service);
                static::assertEquals('capture', $event->apiRequest->action);
                static::assertEquals($event->apiRequest['buy_order'], $buyOrder);
                static::assertEquals($event->apiRequest['authorization_code'], $authorizationCode);
                static::assertEquals($event->apiRequest['capture_amount'], $amount);
                static::assertEquals(
                    new TransactionDetailed('oneclick', 'capture', $transbankResponse), $event->transaction,
                );

                return true;
            },
        );
    }
}
