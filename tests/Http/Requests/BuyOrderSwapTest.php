<?php

namespace Tests\Http\Requests;

use Laragear\Transbank\Http\Requests\WebpayRequest;
use Laragear\Transbank\Services\Webpay;
use Tests\TestCase;
use function route;

/**
 * Regression test for the "buy order swap" exploit.
 *
 * An attacker pays a cheap genuine transaction and replays the callback with a
 * forged `TBK_ORDEN_COMPRA` pointing to a victim order. With `transbank.protect`
 * enabled, the token is still genuine, so protection does not filter the request.
 * The application must key its writes to the committed transaction's buy order,
 * which `WebpayRequest::buyOrder()` now returns on success-shaped requests.
 */
class BuyOrderSwapTest extends TestCase
{
    protected static array $handled = [];

    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('transbank.emulator.enabled', true);
        $app->make('config')->set('transbank.protect.enabled', true);
    }

    protected function defineRoutes($router): void
    {
        // Verbatim handler shape recommended in the README.
        $router->get('confirm', function (WebpayRequest $request) {
            if ($request->isSuccessful()) {
                static::$handled = [
                    'result' => 'PAID',
                    'buy_order' => $request->buyOrder(),
                    'committed_buy_order' => $request->transaction()->get('buy_order'),
                    'amount' => $request->transaction()->amount,
                ];

                return 'success';
            }

            static::$handled = [
                'result' => 'FAILED',
                'buy_order' => $request->buyOrder(),
            ];

            return 'failed';
        })->name('confirm');
    }

    public function test_forged_buy_order_does_not_override_the_committed_transaction(): void
    {
        $webpay = $this->app->make(Webpay::class);

        // 1. The attacker legitimately starts a cheap transaction for their own order.
        $token = $webpay->create('ORDER-ATTACKER', 990, route('confirm'))->getToken();

        // 2. The attacker pays it on the (emulated) Webpay page.
        $this->post('transbank/emulator', ['token_ws' => $token, 'result' => 'approve'])
            ->assertRedirect();

        // 3. The attacker replays the callback with a forged TBK_ORDEN_COMPRA
        //    pointing at a victim order. transbank.protect is ENABLED and lets
        //    this through: the token was genuinely issued by the application.
        $this->get(route('confirm', ['token_ws' => $token, 'TBK_ORDEN_COMPRA' => 'ORDER-VICTIM']))
            ->assertOk();

        static::assertSame('PAID', static::$handled['result']);
        static::assertSame('ORDER-ATTACKER', static::$handled['buy_order']);
        static::assertSame('ORDER-ATTACKER', static::$handled['committed_buy_order']);
        static::assertSame(990, static::$handled['amount']);
    }

    public function test_failed_callback_buy_order_is_the_only_client_supplied_source(): void
    {
        // Default protect behavior aside, a failure-shaped callback never commits.
        $this->get(route('confirm', [
            'TBK_TOKEN' => str_repeat('a', 64),
            'TBK_ORDEN_COMPRA' => 'ORDER-VICTIM',
            'TBK_ID_SESION' => 'attacker-chosen',
        ]))->assertOk();

        static::assertSame('FAILED', static::$handled['result']);
        static::assertSame('ORDER-VICTIM', static::$handled['buy_order']);
    }
}
