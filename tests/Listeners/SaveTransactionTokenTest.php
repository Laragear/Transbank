<?php

namespace Tests\Listeners;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use Laragear\Transbank\ApiRequest;
use Laragear\Transbank\Events\RegistrationStarted;
use Laragear\Transbank\Events\TransactionCreated;
use Laragear\Transbank\Services\Transactions\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaveTransactionTokenTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app->make('config')->set('transbank.protect.enabled', true);
    }

    public static function providesDispatchedEvent(): array
    {
        return [
            [
                function (): void {
                    $this->app->make('events')->dispatch(
                        new TransactionCreated(
                            new ApiRequest('foo', 'bar', ['baz' => 'quz']),
                            new Response('test_token', 'https://app.com/test', 'test_key')
                        )
                    );
                }
            ],
            [
                function (): void {
                    $this->app->make('events')->dispatch(
                        new RegistrationStarted(
                            new ApiRequest('foo', 'bar', ['baz' => 'quz']),
                            new Response('test_token', 'https://app.com/test', 'test_key')
                        )
                    );
                }
            ]
        ];
    }

    #[DataProvider('providesDispatchedEvent')]
    public function test_saves_token_into_cache(Closure $dispatch): void
    {
        $dispatch->call($this);

        static::assertTrue($this->app->make('cache')->has('transbank|token|test_token'));
    }

    #[DataProvider('providesDispatchedEvent')]
    public function test_uses_custom_cache_store(Closure $dispatch): void
    {
        $this->app->make('config')->set('transbank.protect.store', 'foo');

        $this->mock(Factory::class)->expects('store')->with('foo')->andReturnUsing(function () {
            $repository = $this->mock(Repository::class);

            $repository->expects('put')->with('transbank|token|test_token', true, 300);

            return $repository;
        });

        $dispatch->call($this);
    }

    #[DataProvider('providesDispatchedEvent')]
    public function test_uses_custom_cache_prefix(Closure $dispatch): void
    {
        $this->app->make('config')->set('transbank.protect.prefix', 'foo');

        $dispatch->call($this);

        static::assertTrue($this->app->make('cache')->has('foo|test_token'));
    }
}
