<?php

namespace Tests;

use Illuminate\Support\ServiceProvider;
use Laragear\Transbank\Events\TransactionCreated;
use Laragear\Transbank\Http\Client;
use Laragear\Transbank\Http\Middleware\ProtectTransaction;
use Laragear\Transbank\Listeners\SaveTransactionToken;
use Laragear\Transbank\Services\Webpay;
use Laragear\Transbank\TranspayServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_merges_config(): void
    {
        static::assertSame(
            $this->app->make('files')->getRequire(TranspayServiceProvider::CONFIG),
            $this->app->make('config')->get('transbank')
        );
    }

    public function test_registers_http_client(): void
    {
        static::assertTrue($this->app->bound(Client::class));
    }

    public function test_registers_webpay(): void
    {
        static::assertTrue($this->app->bound(Webpay::class));
    }

    public function test_registers_middleware_alias(): void
    {
        $middleware = $this->app->make('router')->getMiddleware();

        static::assertSame(ProtectTransaction::class, $middleware['transbank.protect']);
    }

    public function test_doesnt_registers_listener_when_protection_is_disabled_by_default(): void
    {
        $listeners = $this->app->make('events')->getRawListeners(TransactionCreated::class);

        static::assertArrayNotHasKey(TransactionCreated::class, $listeners);
    }

    /**
     * @define-env enablesProtection
     */
    public function test_registers_listener_when_protection_is_enabled(): void
    {
        $listeners = $this->app->make('events')->getRawListeners(TransactionCreated::class);

        static::assertArrayHasKey(TransactionCreated::class, $listeners);
        static::assertSame([SaveTransactionToken::class], $listeners[TransactionCreated::class]);
    }

    protected function enablesProtection($app)
    {
        $app->make('config')->set('transbank.protect.enabled', true);
    }

    public function test_publishes_config(): void
    {
        static::assertSame([
            TranspayServiceProvider::CONFIG => $this->app->configPath('transbank.php'),
        ], ServiceProvider::pathsToPublish(TranspayServiceProvider::class, 'config'));
    }
}
