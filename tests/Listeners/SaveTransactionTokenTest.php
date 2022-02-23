<?php

namespace Tests\Listeners;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Events\TransactionCreated;
use Laragear\Transbank\Services\Transactions\Response;
use Tests\TestCase;

class SaveTransactionTokenTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app->make('config')->set('transbank.protect.enabled', true);
    }

    protected function dispatch()
    {
        $this->app->make('events')->dispatch(
            new TransactionCreated(
                new ApiRequest('foo', 'bar', ['baz' => 'quz']),
                new Response('test_token', 'https://app.com/test', 'test_key')
            )
        );
    }

    public function test_saves_token_into_cache(): void
    {
        $this->dispatch();

        static::assertTrue($this->app->make('cache')->has('transbank|token|test_token'));
    }

    public function test_uses_custom_cache_store(): void
    {
        $this->app->make('config')->set('transbank.protect.store', 'foo');

        $this->mock(Factory::class)->expects('store')->with('foo')->andReturnUsing(function () {
            $repository = $this->mock(Repository::class);

            $repository->expects('put')->with('transbank|token|test_token', true, 300);

            return $repository;
        });

        $this->dispatch();
    }

    public function test_uses_custom_cache_prefix(): void
    {
        $this->app->make('config')->set('transbank.protect.prefix', 'foo');

        $this->dispatch();

        static::assertTrue($this->app->make('cache')->has('foo|test_token'));
    }
}
