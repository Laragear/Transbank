<?php

namespace Laragear\Transbank\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Laragear\Transbank\Http\Client;
use Psr\Log\LoggerInterface;

class Webpay
{
    use Concerns\FiresEvents;
    use Concerns\DebugsTransactions;
    use Concerns\SendsRequests;

    /**
     * Integrations Keys for this service.
     *
     * @var int
     */
    public const INTEGRATION_KEY = 597055555532;

    // Service names.
    public const SERVICE_NAME = 'webpay';

    public const ACTION_CREATE = 'create';
    public const ACTION_COMMIT = 'commit';
    public const ACTION_STATUS = 'status';
    public const ACTION_REFUND = 'refund';
    public const ACTION_CAPTURE = 'capture';

    /**
     * The API base URI.
     *
     * @var string
     */
    public const ENDPOINT_BASE = 'rswebpaytransaction/api/webpay/{api_version}/';

    // Endpoints for the transactions.
    public const ENDPOINTS = [
        self::ACTION_CREATE =>  ['post', self::ENDPOINT_BASE . 'transactions'],
        self::ACTION_COMMIT =>  ['put', self::ENDPOINT_BASE . 'transactions/{token}'],
        self::ACTION_STATUS =>  ['get', self::ENDPOINT_BASE . 'transactions/{token}'],
        self::ACTION_REFUND =>  ['put', self::ENDPOINT_BASE . 'transactions/{token}/refunds'],
        self::ACTION_CAPTURE => ['put', self::ENDPOINT_BASE . 'transactions/{token}/capture'],
    ];

    /**
     * Create a new Webpay instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $event
     * @param  \Psr\Log\LoggerInterface  $logger
     * @param  \Laragear\Transbank\Http\Client  $client
     */
    public function __construct(public Dispatcher $event, public LoggerInterface $logger, public Client $client)
    {
        //
    }

    /**
     * Creates a ApiRequest on Transbank, returns a response from it.
     *
     * @param  string  $buyOrder
     * @param  int|float  $amount
     * @param  string  $returnUrl
     * @return \Laragear\Transbank\Services\Transactions\Response
     */
    public function create(string $buyOrder, int|float $amount, string $returnUrl): Transactions\Response
    {
        $apiRequest = $this->request(static::ACTION_CREATE, [
            'buy_order' => $buyOrder,
            'amount' => $amount,
            'session_id' => 'no-session-id',
            'return_url' => $returnUrl,
        ]);

        $this->logCreating($apiRequest);
        $this->fireCreating($apiRequest);

        $response = $this->send($apiRequest);

        $transbankResponse = new Transactions\Response($response->json('token'), $response->json('url'));

        $this->fireCreated($apiRequest, $transbankResponse);
        $this->logResponse($apiRequest, $response);

        return $transbankResponse;
    }

    /**
     * Commits a transaction immediately
     *
     * @param  string  $token
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     */
    public function commit(string $token): Transactions\Transaction
    {
        $apiRequest = $this->request(static::ACTION_COMMIT);

        $this->log('Committing transaction', ['token' => $token, 'api_request' => $apiRequest]);

        $response = $this->send($apiRequest, ['{token}' => $token]);

        $transaction = $this->transaction(static::ACTION_COMMIT, $response);

        $this->logResponse($apiRequest, $response, $token);
        $this->fireCompleted($apiRequest, $transaction);

        return $transaction;
    }

    /**
     * Returns the status of a non-expired transaction by its token.
     *
     * @param  string  $token
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     */
    public function status(string $token): Transactions\Transaction
    {
        $apiRequest = $this->request(self::ACTION_STATUS);

        $this->log('Retrieving transaction status', ['token' => $token, 'api_request' => $apiRequest]);

        $response = $this->send($apiRequest, ['{token}' => $token]);

        $this->logResponse($apiRequest, $response, $token);

        return $this->transaction(static::ACTION_STATUS, $response);
    }

    /**
     * Refunds partially or totally a given credit-card charge amount.
     *
     * @param  string  $token
     * @param  int|float  $amount
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     *
     */
    public function refund(string $token, int|float $amount): Transactions\Transaction
    {
        $apiRequest = $this->request(static::ACTION_REFUND, ['amount' => $amount]);

        $this->log('Refunding transaction', ['token' => $token, 'api_request' => $apiRequest]);
        $this->fireCreating($apiRequest);

        $response = $this->send($apiRequest, ['{token}' => $token]);

        $transaction = $this->transaction(static::ACTION_REFUND, $response);

        $this->logResponse($apiRequest, $response, $token);
        $this->fireCompleted($apiRequest, $transaction);

        return $transaction;
    }

    /**
     * Creates a Capture ApiRequest on Transbank servers, returns a response.
     *
     * This transaction type only works for credit cards, and "holds" the amount up to 7 days.
     *
     * @param  string  $token
     * @param  string  $buyOrder
     * @param  int  $code
     * @param  int|float  $amount
     * @return \Laragear\Transbank\Services\Transactions\Transaction
     *
     */
    public function capture(string $token, string $buyOrder, int $code, int|float $amount): Transactions\Transaction
    {
        $apiRequest = $this->request(static::ACTION_CAPTURE, [
            'buy_order' => $buyOrder,
            'authorization_code' => $code,
            'capture_amount' => $amount,
        ]);

        $this->log('Capturing transaction', ['token' => $token, 'api_request' => $apiRequest]);

        $response = $this->send($apiRequest, ['{token}' => $token]);

        $transaction = $this->transaction(static::ACTION_CAPTURE, $response);

        $this->logResponse($apiRequest, $response, $token);
        $this->fireCompleted($apiRequest, $transaction);

        return $transaction;
    }
}
