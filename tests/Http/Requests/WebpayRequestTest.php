<?php

namespace Tests\Http\Requests;

use Illuminate\Support\Str;
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Http\Requests\WebpayRequest;
use Laragear\Transbank\Services\Transactions\Transaction;
use Tests\TestCase;

class WebpayRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WebpayRequest::$validate = false;
    }

    protected function tearDown(): void
    {
        WebpayRequest::$validate = false;

        parent::tearDown();
    }

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

        Webpay::shouldReceive('commit')->with('baz')->never();

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return [
                $request->isSuccessful() ? 'true' : 'false',
                $request->isNotSuccessful() ? 'true' : 'false',
            ];
        });

        $this->get('confirm?token_ws=foo')->assertOk()->assertJson(['true', 'false']);
        $this->get('confirm?token_ws=bar')->assertOk()->assertJson(['false', 'true']);
        $this->get('confirm?TBK_TOKEN=baz')->assertOk()->assertJson(['false', 'true']);
        $this->get('confirm')->assertOk()->assertJson(['false', 'true']);
    }

    public function test_checks_if_request_is_error(): void
    {
        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return [
                $request->isError() ? 'true' : 'false',
                $request->isNotError() ? 'true' : 'false',
            ];
        });

        $this->get('confirm')->assertOk()->assertJson(['true', 'false']);
        $this->get('confirm?TBK_TOKEN=bar')->assertOk()->assertJson(['true', 'false']);

        $this->get('confirm?token_ws=foo')->assertOk()->assertJson(['false', 'true']);
    }

    public function test_returns_transaction_buy_order_from_successful_transaction(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->andReturn(
            new Transaction('foo', 'bar', ['response_code' => 0, 'buy_order' => 'bar'])
        );

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return [
                'buy-order' => $request->buyOrder()
            ];
        });

        $this->get('confirm?token_ws=foo')->assertOk()->assertJson(['buy-order' => 'bar']);
    }

    public function test_returns_transaction_buy_order_from_failed_response(): void
    {
        Webpay::shouldReceive('commit')->once()->with('foo')->never();

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return [
                'buy-order' => $request->buyOrder()
            ];
        });

        $this->get('confirm?TBK_TOKEN=foo&TBK_ORDEN_COMPRA=bar')->assertOk()->assertJson(['buy-order' => 'bar']);
    }

    public function test_validates_request(): void
    {
        WebpayRequest::$validate = true;

        $token = Str::random(64);

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return 'ok';
        });

        $this->get("confirm?token_ws=$token")->assertOk();
        $this->get("confirm?TBK_TOKEN=$token&TBK_ORDEN_COMPRA=bar")->assertOk();
        $this->get('confirm')->assertRedirect();
        $this->get('confirm?token_ws=foo')->assertRedirect();
        $this->get('confirm?TBK_TOKEN=foo')->assertRedirect();
        $this->get('confirm?TBK_TOKEN=foo&TBK_ORDEN_COMPRA=bar')->assertRedirect();
    }

    public function test_validates_request_and_redirects_to_custom_path(): void
    {
        WebpayRequest::$validate = function (WebpayRequest $request) {
            return '/custom/path';
        };

        $token = Str::random(64);

        $this->app->make('router')->get('confirm', function (WebpayRequest $request) {
            return 'ok';
        });

        $this->get("confirm?token_ws=$token")->assertOk();
        $this->get("confirm?TBK_TOKEN=$token&TBK_ORDEN_COMPRA=bar")->assertOk();
        $this->get('confirm')->assertRedirect('/custom/path');
        $this->get('confirm?token_ws=foo')->assertRedirect('/custom/path');
        $this->get('confirm?TBK_TOKEN=foo')->assertRedirect('/custom/path');
        $this->get('confirm?TBK_TOKEN=foo&TBK_ORDEN_COMPRA=bar')->assertRedirect('/custom/path');
    }
}
