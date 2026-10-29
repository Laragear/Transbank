<?php

namespace Tests\Http\Emulator;

use Laragear\Transbank\Http\Emulator\Store;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Tests\TestCase;

class EmulatorControllerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('transbank.emulator.enabled', true);
    }

    protected function seedTransaction(): void
    {
        $this->app->make(Store::class)->put('test-token', [
            'amount' => 1000,
            'buy_order' => 'buy-order-1',
            'session_id' => 'session-1',
            'return_url' => 'http://localhost/return',
        ]);
    }

    public function test_shows_emulator_page_with_token(): void
    {
        $this->seedTransaction();

        $this->get('transbank/emulator?token_ws=test-token')
            ->assertOk()
            ->assertSee('test-token', false)
            ->assertSee('name="result"', false)
            ->assertSee('Pay');
    }

    public function test_shows_emulator_page_not_found_with_unknown_token(): void
    {
        $this->get('transbank/emulator?token_ws=unknown')->assertNotFound();
    }

    public function test_submit_approve_returns_token_ws(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', ['token_ws' => 'test-token', 'result' => 'approve'])
            ->assertRedirect('http://localhost/return?token_ws=test-token');
    }

    public function test_submit_reject_returns_token_ws(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', ['token_ws' => 'test-token', 'result' => 'reject'])
            ->assertRedirect('http://localhost/return?token_ws=test-token');
    }

    public function test_submit_abort_returns_tbk_trio(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', ['token_ws' => 'test-token', 'result' => 'abort'])
            ->assertRedirect(
                'http://localhost/return?TBK_TOKEN=test-token&TBK_ORDEN_COMPRA=buy-order-1&TBK_ID_SESION=session-1'
            );
    }

    public function test_submit_fail_returns_all_params(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', ['token_ws' => 'test-token', 'result' => 'fail'])
            ->assertRedirect(
                'http://localhost/return?token_ws=test-token&TBK_TOKEN=test-token&TBK_ORDEN_COMPRA=buy-order-1&TBK_ID_SESION=session-1'
            );
    }

    public function test_submit_falls_back_to_approve_with_invalid_result(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', ['token_ws' => 'test-token', 'result' => 'quz'])
            ->assertRedirect('http://localhost/return?token_ws=test-token');
    }

    public function test_submit_forces_post_with_interstitial(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', [
            'token_ws' => 'test-token',
            'result' => 'abort',
            'method' => 'post',
        ])
            ->assertOk()
            ->assertSee('action="http://localhost/return"', false)
            ->assertSee('name="TBK_TOKEN" value="test-token"', false)
            ->assertSee('name="TBK_ORDEN_COMPRA" value="buy-order-1"', false)
            ->assertSee('name="TBK_ID_SESION" value="session-1"', false);
    }

    public function test_submit_forces_token_parameter_name(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', [
            'token_ws' => 'test-token',
            'result' => 'reject',
            'token_key' => 'TOKEN_WS',
        ])
            ->assertRedirect('http://localhost/return?TOKEN_WS=test-token');
    }

    public function test_submit_includes_all_tbk_variables(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', [
            'token_ws' => 'test-token',
            'result' => 'approve',
            'tbk' => 'all',
        ])
            ->assertRedirect(
                'http://localhost/return?token_ws=test-token&TBK_TOKEN=test-token&TBK_ORDEN_COMPRA=buy-order-1&TBK_ID_SESION=session-1'
            );
    }

    public function test_submit_excludes_tbk_variables(): void
    {
        $this->seedTransaction();

        $this->post('transbank/emulator', [
            'token_ws' => 'test-token',
            'result' => 'abort',
            'tbk' => 'none',
        ])
            ->assertRedirect('http://localhost/return?TBK_TOKEN=test-token');
    }

    public function test_submit_not_found_with_unknown_token(): void
    {
        $this->post('transbank/emulator', ['token_ws' => 'unknown'])->assertNotFound();
    }
}
