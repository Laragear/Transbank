<?php

namespace Tests\Http\Requests;

use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Http\Requests\WebpayRequest;
use Laragear\Transbank\Services\Transactions\Transaction;
use Tests\TestCase;

class WebpayRequestTest extends TestCase
{
    public function test_commits_transaction(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->transaction();
        });

        $this->get('confirm?token_ws=foo')->assertJson(['baz' => 'quz']);
    }

    public function test_commits_transaction_only_once(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            $request->transaction();
            return $request->transaction();
        });

        $this->get('confirm?token_ws=foo')->assertJson(['baz' => 'quz']);
    }

    public function test_commits_transaction_when_truthy(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitWhen(1);
        });

        $this->get('confirm?token_ws=foo')->assertJson(['baz' => 'quz']);
    }

    public function test_commits_transaction_when_callback_truthy_using_status(): void
    {
        Webpay::shouldReceive('status')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['qux' => 'quux'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitWhen(static function (Transaction $status): bool {
                return $status->getBaz() === 'quz';
            });
        });

        $this->get('confirm?token_ws=foo')->assertJson(['qux' => 'quux']);
    }

    public function test_doesnt_commits_transaction_when_not_truthy(): void
    {
        Webpay::shouldReceive('commit')->never();

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitWhen('') ?? 'not-committed';
        });

        $this->get('confirm?token_ws=foo')->assertSee('not-committed')->assertOk();
    }

    public function test_doesnt_commits_transaction_when_callback_not_truthy_using_status(): void
    {
        Webpay::shouldReceive('status')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );
        Webpay::shouldReceive('commit')->never();

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitWhen(static function (Transaction $status): bool {
                return false;
            }) ?? 'not-committed';
        });

        $this->get('confirm?token_ws=foo')->assertSee('not-committed')->assertOk();
    }

    public function test_commits_transaction_unless_falsy(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitUnless(0);
        });

        $this->get('confirm?token_ws=foo')->assertJson(['baz' => 'quz']);
    }

    public function test_commits_transaction_unless_callback_falsy_using_status(): void
    {
        Webpay::shouldReceive('status')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['qux' => 'quux'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitUnless(static function (Transaction $status): bool {
                return $status->getBaz() !== 'quz';
            });
        });

        $this->get('confirm?token_ws=foo')->assertJson(['qux' => 'quux']);
    }

    public function test_doesnt_commits_transaction_unless_not_falsy(): void
    {
        Webpay::shouldReceive('commit')->never();

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitUnless('asd') ?? 'not-committed';
        });

        $this->get('confirm?token_ws=foo')->assertSee('not-committed')->assertOk();
    }

    public function test_doesnt_commits_transaction_unless_callback_not_falsy_using_status(): void
    {
        Webpay::shouldReceive('status')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['baz' => 'quz'])
        );
        Webpay::shouldReceive('commit')->never();

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return $request->commitUnless(static function (Transaction $status): bool {
                return true;
            }) ?? 'not-committed';
        });

        $this->get('confirm?token_ws=foo')->assertSee('not-committed')->assertOk();
    }

    public function test_checks_transaction_is_successful(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['response_code' => 0])
        );

        Webpay::shouldReceive('commit')->once()->with('bar')->andReturn(
            new Transaction('foo', 'bar', ['response_code' => 1])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return [
                $request->isSuccessful() ? 'true' : 'false',
                $request->isNotSuccessful() ? 'true' : 'false',
            ];
        });

        $this->get('confirm?token_ws=foo')->assertOk()->assertJson(['true', 'false']);
        $this->get('confirm?token_ws=bar')->assertOk()->assertJson(['false', 'true']);
    }
}
