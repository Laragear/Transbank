<?php

namespace Tests\Http\Middleware;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Mockery;
use Tests\TestCase;

class ProtectTransactionTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('confirm', function () {
            return 'ok';
        })->middleware('transbank.protect');

        $router->post('confirm', function () {
            return 'ok';
        })->middleware('transbank.protect');
    }

    public function test_aborts_if_token_absent(): void
    {
        $this->get('confirm')->assertNotFound();
        $this->post('confirm')->assertNotFound();
    }

    public function test_accepts_if_token_present(): void
    {
        $this->get('confirm?token_ws=foo')->assertOk();
        $this->post('confirm', ['token_ws' => 'foo'])->assertOk();

        $this->get('confirm?TBK_TOKEN=foo')->assertOk();
        $this->post('confirm', ['TBK_TOKEN' => 'foo'])->assertOk();
    }

    public function test_aborts_if_token_not_saved_previously(): void
    {
        $this->app->make('config')->set('transbank.protect.enabled', true);

        $this->get('confirm?token_ws=foo')->assertNotFound();
        $this->post('confirm', ['token_ws' => 'foo'])->assertNotFound();

        $this->get('confirm?TBK_TOKEN=foo')->assertNotFound();
        $this->post('confirm', ['TBK_TOKEN' => 'foo'])->assertNotFound();
    }

    public function test_accepts_once_if_webpay_token_saved_previously(): void
    {
        $this->app->make('config')->set('transbank.protect.enabled', true);

        $this->app->make('cache')->put('transbank|token|foo', true);
        $this->app->make('cache')->put('transbank|token|bar', true);
        $this->app->make('cache')->put('transbank|token|baz', true);
        $this->app->make('cache')->put('transbank|token|quz', true);

        $this->get('confirm?token_ws=foo')->assertOk();
        $this->get('confirm?token_ws=foo')->assertNotFound();

        $this->post('confirm', ['token_ws' => 'bar'])->assertOk();
        $this->post('confirm', ['token_ws' => 'bar'])->assertNotFound();

        $this->get('confirm?TBK_TOKEN=baz')->assertOk();
        $this->get('confirm?TBK_TOKEN=baz')->assertNotFound();

        $this->post('confirm', ['TBK_TOKEN' => 'quz'])->assertOk();
        $this->post('confirm', ['TBK_TOKEN' => 'quz'])->assertNotFound();
    }

    public function test_uses_custom_cache_store(): void
    {
        $mock = Mockery::mock(Repository::class);

        $mock->expects('pull')
            ->with('transbank|token|foo')
            ->times(8)
            ->andReturn(true, false, true, false, true, false, true, false);

        $this->swap('cache', Mockery::mock(Factory::class))
            ->expects('store')
            ->with('foo')
            ->times(8)
            ->andReturn($mock);

        $this->app->make('config')->set([
            'transbank.protect.enabled' => true,
            'transbank.protect.store' => 'foo',
        ]);

        $this->get('confirm?token_ws=foo')->assertOk();
        $this->get('confirm?token_ws=foo')->assertNotFound();

        $this->post('confirm', ['token_ws' => 'foo'])->assertOk();
        $this->post('confirm', ['token_ws' => 'foo'])->assertNotFound();

        $this->get('confirm?TBK_TOKEN=foo')->assertOk();
        $this->get('confirm?TBK_TOKEN=foo')->assertNotFound();

        $this->post('confirm', ['TBK_TOKEN' => 'foo'])->assertOk();
        $this->post('confirm', ['TBK_TOKEN' => 'foo'])->assertNotFound();
    }

    public function test_uses_custom_cache_prefix(): void
    {
        $mock = Mockery::mock(Repository::class);

        $mock->expects('pull')
            ->with('test_prefix|foo')
            ->times(8)
            ->andReturn(true, false, true, false, true, false, true, false);

        $this->swap('cache', Mockery::mock(Factory::class))
            ->expects('store')
            ->with(null)
            ->times(8)
            ->andReturn($mock);

        $this->app->make('config')->set([
            'transbank.protect.enabled' => true,
            'transbank.protect.prefix' => 'test_prefix',
        ]);

        $this->get('confirm?token_ws=foo')->assertOk();
        $this->get('confirm?token_ws=foo')->assertNotFound();

        $this->post('confirm', ['token_ws' => 'foo'])->assertOk();
        $this->post('confirm', ['token_ws' => 'foo'])->assertNotFound();

        $this->get('confirm?TBK_TOKEN=foo')->assertOk();
        $this->get('confirm?TBK_TOKEN=foo')->assertNotFound();

        $this->post('confirm', ['TBK_TOKEN' => 'foo'])->assertOk();
        $this->post('confirm', ['TBK_TOKEN' => 'foo'])->assertNotFound();
    }
}
