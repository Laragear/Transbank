<?php

namespace Laragear\Transbank\Http\Emulator;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use function array_merge;
use function bin2hex;
use function parse_url;
use function preg_match;
use function random_bytes;
use function random_int;

class FakeResponses
{
    /**
     * Creates a new FakeResponses instance.
     */
    public function __construct(protected Store $store)
    {
        //
    }

    /**
     * Handle the request to Transbank servers with an emulated response.
     */
    public function __invoke(HttpRequest $request): PromiseInterface
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        $response = match (true) {
            $request->method() === 'POST' && Str::endsWith($path, '/transactions') => $this->createTransaction($request),
            $request->method() === 'PUT' && $this->isTransactionToken($path) => $this->commitTransaction($path),
            $request->method() === 'GET' && $this->isTransactionToken($path) => $this->transactionStatus($path),
            default => $this->notEmulated(),
        };

        // The stub pipeline expects the promise to resolve to the PSR-7 response.
        return Create::promiseFor($response->toPsrResponse());
    }

    /**
     * Respond to a transaction creation with a token and the local emulator URL.
     */
    protected function createTransaction(HttpRequest $request): Response
    {
        $token = bin2hex(random_bytes(32));

        $data = $request->data();

        $this->store->put($token, [
            'amount' => $data['amount'] ?? null,
            'buy_order' => $data['buy_order'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'return_url' => $data['return_url'] ?? null,
        ]);

        return $this->respond(['token' => $token, 'url' => route('transbank.emulator.show')]);
    }

    /**
     * Respond to a transaction confirmation according to the selected result.
     */
    protected function commitTransaction(string $path): Response
    {
        $token = $this->tokenFromPath($path);

        $transaction = $this->store->get($token);

        if (! $transaction) {
            return $this->notFound();
        }

        $response = match ($transaction['result'] ?? null) {
            'approve' => $this->authorized($transaction),
            'reject' => $this->rejected($transaction),
            // "abort" and "fail" transactions are refused by the real servers on commit.
            default => $this->aborted(),
        };

        if ($response->status() === 200) {
            $transaction['committed'] = $response->json();

            $this->store->put($token, $transaction);
        }

        return $response;
    }

    /**
     * Respond to a transaction status with the current state of the transaction.
     */
    protected function transactionStatus(string $path): Response
    {
        $transaction = $this->store->get($this->tokenFromPath($path));

        if (! $transaction) {
            return $this->notFound();
        }

        if (isset($transaction['committed'])) {
            return $this->respond($transaction['committed']);
        }

        return $this->respond([
            'amount' => $transaction['amount'],
            'status' => 'INITIALIZED',
            'buy_order' => $transaction['buy_order'],
            'session_id' => $transaction['session_id'],
            'card_detail' => ['card_number' => '0000'],
            'accounting_date' => now()->format('md'),
            'transaction_date' => now()->toISOString(),
            'installments_number' => 0,
        ]);
    }

    /**
     * Build the response of an authorized transaction.
     */
    protected function authorized(array $transaction): Response
    {
        return $this->respond([
            ...$this->transactionBase($transaction),
            'vci' => 'TSY',
            'status' => 'AUTHORIZED',
            'authorization_code' => (string) random_int(1000, 9999),
            'payment_type_code' => 'VD',
            'response_code' => 0,
        ]);
    }

    /**
     * Build the response of a rejected transaction.
     */
    protected function rejected(array $transaction): Response
    {
        return $this->respond([
            ...$this->transactionBase($transaction),
            'vci' => 'TSY',
            'status' => 'FAILED',
            'authorization_code' => '000000',
            'payment_type_code' => 'VD',
            'response_code' => -1,
        ]);
    }

    /**
     * Common attributes of a confirmed transaction.
     */
    protected function transactionBase(array $transaction): array
    {
        return [
            'amount' => $transaction['amount'],
            'buy_order' => $transaction['buy_order'],
            'session_id' => $transaction['session_id'],
            'card_detail' => ['card_number' => '0000'],
            'accounting_date' => now()->format('md'),
            'transaction_date' => now()->toISOString(),
            'installments_number' => 0,
        ];
    }

    /**
     * The real servers refuse to confirm aborted transactions with a 422.
     */
    protected function aborted(): Response
    {
        return $this->respond(
            ['error_message' => 'Transaction has an invalid finished state: aborted'],
            422,
        );
    }

    /**
     * The token is unknown to the emulator or has expired.
     */
    protected function notFound(): Response
    {
        return $this->respond(['error_message' => 'The transaction does not exist.'], 404);
    }

    /**
     * The emulator only handles the transaction endpoints.
     */
    protected function notEmulated(): Response
    {
        return $this->respond(['error_message' => 'This endpoint is not emulated.'], 501);
    }

    /**
     * Create an HTTP response from the emulated body.
     */
    protected function respond(array $body, int $status = 200): Response
    {
        return new Response(HttpFactory::psr7Response($body, $status));
    }

    /**
     * Check if the path points to a transaction token.
     */
    protected function isTransactionToken(string $path): bool
    {
        return (bool) preg_match('#/transactions/[^/]+$#', $path);
    }

    /**
     * Extract the transaction token from the request path.
     */
    protected function tokenFromPath(string $path): string
    {
        return Str::afterLast($path, '/transactions/');
    }
}
