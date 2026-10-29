<?php

namespace Tests\Http\Emulator;

use Illuminate\Http\Client\RequestException;
use Laragear\Transbank\Services\Webpay;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Tests\TestCase;
use function route;

class EmulatorTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('transbank.emulator.enabled', true);
    }

    protected function createTransaction(Webpay $webpay): string
    {
        $response = $webpay->create('order-1', 1000, 'http://localhost/return');

        static::assertSame('http://localhost/transbank/emulator', $response->getUrl());
        static::assertSame(route('transbank.emulator.show').'?token_ws='.$response->getToken(), $response->toString());

        return $response->getToken();
    }

    public function test_create_returns_local_emulator_url(): void
    {
        $this->createTransaction($this->app->make(Webpay::class));
    }

    public function test_commit_approves_selected_result(): void
    {
        $webpay = $this->app->make(Webpay::class);
        $token = $this->createTransaction($webpay);

        $this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'approve'])
            ->assertRedirect();

        $transaction = $webpay->commit($token);

        static::assertTrue($transaction->isSuccessful());
        static::assertSame('AUTHORIZED', $transaction->status);
        static::assertSame(0, $transaction->response_code);
        static::assertSame('order-1', $transaction->buy_order);
        static::assertSame(1000, $transaction->amount);
        static::assertSame(0, $transaction->getCreditCardNumber());
    }

    public function test_commit_rejects_selected_result(): void
    {
        $webpay = $this->app->make(Webpay::class);
        $token = $this->createTransaction($webpay);

        $this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'reject'])
            ->assertRedirect();

        $transaction = $webpay->commit($token);

        static::assertFalse($transaction->isSuccessful());
        static::assertSame('FAILED', $transaction->status);
        static::assertSame(-1, $transaction->response_code);
        static::assertSame('000000', $transaction->authorization_code);
    }

    public function test_commit_aborted_result_is_refused(): void
    {
        $webpay = $this->app->make(Webpay::class);
        $token = $this->createTransaction($webpay);

        $this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'abort'])
            ->assertRedirect();

        $transaction = $webpay->commit($token);

        static::assertFalse($transaction->isSuccessful());
        static::assertSame(
            'Transaction has an invalid finished state: aborted',
            $transaction->error_message
        );
    }

    public function test_commit_failed_result_is_refused(): void
    {
        $webpay = $this->app->make(Webpay::class);
        $token = $this->createTransaction($webpay);

        $this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'fail'])
            ->assertRedirect();

        $transaction = $webpay->commit($token);

        static::assertFalse($transaction->isSuccessful());
        static::assertSame(
            'Transaction has an invalid finished state: aborted',
            $transaction->error_message
        );
    }

    public function test_commit_unknown_token_throws_request_exception(): void
    {
        $webpay = $this->app->make(Webpay::class);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('The transaction does not exist.');

        $webpay->commit('unknown-token');
    }

    public function test_status_is_initialized_before_the_result_is_selected(): void
    {
        $webpay = $this->app->make(Webpay::class);
        $token = $this->createTransaction($webpay);

        $transaction = $webpay->status($token);

        static::assertFalse($transaction->isSuccessful());
        static::assertSame('INITIALIZED', $transaction->status);
        static::assertSame('order-1', $transaction->buy_order);
    }

    public function test_status_after_commit_returns_the_committed_result(): void
    {
        $webpay = $this->app->make(Webpay::class);
        $token = $this->createTransaction($webpay);

        $this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'approve'])
            ->assertRedirect();

        $webpay->commit($token);

        $transaction = $webpay->status($token);

        static::assertTrue($transaction->isSuccessful());
        static::assertSame('AUTHORIZED', $transaction->status);
    }
}
