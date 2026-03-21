<?php

namespace Laragear\Transbank\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Arrayable;
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
use Psr\Log\LoggerInterface;

class Oneclick
{
    use Concerns\FiresEvents;
    use Concerns\DebugsTransactions;
    use Concerns\SendsRequests;

    /**
     * Integrations Keys for this service.
     */
    public const int INTEGRATION_KEY = 597055555541;
    public const int INTEGRATION_CHILD_KEY = 597055555542;
    public const int INTEGRATION_CHILD_KEY_ALTERNATIVE = 597055555543;

    // Service names.
    public const string SERVICE_NAME = 'oneclick';

    // Action names
    public const string ACTION_REGISTER = 'register';
    public const string ACTION_CONFIRM = 'confirm';
    public const string ACTION_DELETE = 'delete';

    public const string ACTION_COMMIT = 'commit';
    public const string ACTION_STATUS = 'status';
    public const string ACTION_REFUND = 'refund';
    public const string ACTION_CAPTURE = 'capture';

    // The API base URI.
    public const string ENDPOINT_BASE = 'rswebpaytransaction/api/oneclick/{api_version}/';

    // Endpoints for the transactions.
    public const array ENDPOINTS = [
        self::ACTION_REGISTER => ['post', self::ENDPOINT_BASE.'inscriptions'],
        self::ACTION_CONFIRM => ['put', self::ENDPOINT_BASE.'inscriptions/{tbkUser}'],
        self::ACTION_DELETE => ['delete', self::ENDPOINT_BASE.'inscriptions'],

        self::ACTION_COMMIT => ['post', self::ENDPOINT_BASE.'transactions'],
        self::ACTION_STATUS => ['get', self::ENDPOINT_BASE.'transactions/{buyOrder}'],
        self::ACTION_REFUND => ['post', self::ENDPOINT_BASE.'transactions/{buyOrder}/refunds'],
        self::ACTION_CAPTURE => ['put', self::ENDPOINT_BASE.'transactions/capture'],
    ];

    /**
     * Create a new Oneclick instance.
     */
    public function __construct(public Dispatcher $event, public LoggerInterface $logger, public Client $client)
    {
        //
    }

    /**
     * Start the registration of a card in Transbank.
     */
    public function register(string $username, string $email, string $responseUrl): Transactions\Response
    {
        $apiRequest = $this->request(static::ACTION_REGISTER, [
            'username' => $username,
            'email' => $email,
            'response_url' => $responseUrl,
        ]);

        $this->logger->debug('Creating registration', ['api_request' => $apiRequest]);
        $this->event->dispatch(new RegistrationStarting($apiRequest));

        $response = $this->send($apiRequest);

        $transbankResponse = new Transactions\Response(
            $response->json('token'),
            $response->json('url_webpay'),
            Transactions\Response::ONECLICK_TOKEN,
        );

        $this->logResponse($apiRequest, $response);
        $this->event->dispatch(new RegistrationStarted($apiRequest, $transbankResponse));

        return $transbankResponse;
    }

    /**
     * Confirms a registration.
     */
    public function confirm(string $tbkUser): Transactions\Transaction
    {
        $apiRequest = $this->request(static::ACTION_CONFIRM);

        $this->logger->debug('Finishing registration', ['tbkUser' => $tbkUser, 'api_request' => $apiRequest]);
        $this->event->dispatch(new RegistrationFinishing($apiRequest));

        $response = $this->send($apiRequest, ['{tbkUser}' => $tbkUser]);

        $transaction = $this->transaction(static::ACTION_CONFIRM, $response);

        $this->logResponse($apiRequest, $response, $tbkUser);
        $this->event->dispatch(new RegistrationFinished($apiRequest, $transaction));

        return $transaction;
    }

    /**
     * Deletes a registration.
     */
    public function delete(string $tbkUser, string $username): void
    {
        $apiRequest = $this->request(static::ACTION_DELETE, [
            'tbk_user' => $tbkUser,
            'username' => $username,
        ]);

        $this->logger->debug('Deleting registration', ['api_request' => $apiRequest]);
        $this->event->dispatch(new RegistrationDeleting($apiRequest));

        $response = $this->send($apiRequest);

        $this->logResponse($apiRequest, $response, $tbkUser);
        $this->event->dispatch(new RegistrationDeleted($apiRequest));
    }

    /**
     * Authorizes a charge.
     *
     * @param iterable<\Laragear\Transbank\Services\Transactions\Detail|array{commerce_code: string|int, buy_order: string, amount: int|float, installmens_number?: int|null}>  $details
     */
    public function commit(
        string $tbkUser,
        string $username,
        string $buyOrder,
        iterable $details,
    ): Transactions\TransactionDetailed {
        $apiRequest = $this->request(static::ACTION_COMMIT, [
            'tbk_user' => $tbkUser,
            'username' => $username,
            'buy_order' => $buyOrder,
            'details' => $this->normalizeDetails($details),
        ]);

        $this->logger->debug('Committing transaction', ['tbk_user' => $tbkUser, 'api_request' => $apiRequest]);
        $this->event->dispatch(new TransactionDetailedCharging($apiRequest));

        $response = $this->send($apiRequest);

        $transaction = $this->transactionDetailed(static::ACTION_COMMIT, $response);

        $this->logger->debug('Response received', [
            'api_request' => $apiRequest,
            'raw_response' => $response->json(),
            'tbk_user' => $tbkUser,
        ]);

        $this->event->dispatch(new TransactionDetailedCharged($apiRequest, $transaction));

        return $transaction;
    }

    /**
     * Normalize the iterable details.
     *
     * @return array{commerce_code: string|int, buy_order: string, amount: int|float, installmens_number?: int|null}[]
     */
    protected function normalizeDetails(iterable $details): array
    {
        $array = [];

        foreach ($details as $detail) {
            $array[] = $detail instanceof Arrayable ? $detail->toArray() : $detail;
        }

        return $array;
    }

    /**
     * Checks the state of a transaction.
     */
    public function status(string $buyOrder): Transactions\TransactionDetailed
    {
        $apiRequest = $this->request(static::ACTION_STATUS);

        $this->logger->debug('Retrieving transaction status', ['buy_order' => $buyOrder, 'api_request' => $apiRequest]);
        $this->event->dispatch(new TransactionDetailedRetrieving($apiRequest, $buyOrder));

        $response = $this->send($apiRequest, ['{buyOrder}' => $buyOrder]);

        $transaction = $this->transactionDetailed(static::ACTION_STATUS, $response);

        $this->logger->debug('Response received', [
            'api_request' => $apiRequest,
            'raw_response' => $response->json(),
            'buy_order' => $buyOrder,
        ]);

        $this->event->dispatch(new TransactionDetailedRetrieved($apiRequest, $transaction));

        return $transaction;
    }

    /**
     * Refunds a buy order by the given detailed buy order.
     */
    public function refund(
        string $buyOrder,
        string $commerceCode,
        string $detailBuyOrder,
        int|float $amount,
    ): Transactions\TransactionDetailed {
        $apiRequest = $this->request(static::ACTION_REFUND, [
            'buy_order' => $buyOrder,
            'commerce_code' => $commerceCode,
            'detail_buy_order' => $detailBuyOrder,
            'amount' => $amount,
        ]);

        $this->logger->debug('Refunding transaction', ['api_request' => $apiRequest, 'buy_order' => $buyOrder]);
        $this->event->dispatch(new TransactionDetailedRefunding($apiRequest));

        $response = $this->send($apiRequest, ['{buyOrder}' => $buyOrder]);

        $transaction = $this->transactionDetailed(static::ACTION_REFUND, $response);

        $this->logger->debug('Response received', [
            'api_request' => $apiRequest,
            'raw_response' => $response->json(),
            'buy_order' => $buyOrder,
        ]);
        $this->event->dispatch(new TransactionDetailedRefunded($apiRequest, $transaction));

        return $transaction;
    }

    /**
     * Captures an authorized transaction.
     */
    public function capture(
        string $buyOrder,
        string $commerceCode,
        string $authorizationCode,
        int|float $amount,
    ): Transactions\TransactionDetailed {
        $apiRequest = $this->request(static::ACTION_CAPTURE, [
            'commerce_code' => $commerceCode,
            'buy_order' => $buyOrder,
            'capture_amount' => $amount,
            'authorization_code' => $authorizationCode,
        ]);

        $this->logger->debug('Capturing transaction', ['api_request' => $apiRequest]);
        $this->event->dispatch(new TransactionDetailedCapturing($apiRequest));

        $response = $this->send($apiRequest);

        $transaction = $this->transactionDetailed(static::ACTION_CAPTURE, $response);

        $this->logger->debug('Response received', [
            'api_request' => $apiRequest,
            'raw_response' => $response->json(),
            'buy_order' => $buyOrder,
        ]);
        $this->event->dispatch(new TransactionDetailedCaptured($apiRequest, $transaction));

        return $transaction;
    }
}
