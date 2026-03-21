<?php

namespace Tests;

use Illuminate\Support\ServiceProvider;
use Laragear\MetaTesting\InteractsWithServiceProvider;
use Laragear\Transbank\Events\TransactionCreated;
use Laragear\Transbank\Http\Client;
use Laragear\Transbank\Http\Middleware\ProtectTransaction;
use Laragear\Transbank\Listeners\SaveTransactionToken;
use Laragear\Transbank\Services\Oneclick;
use Laragear\Transbank\Services\Webpay;
use Laragear\Transbank\TransbankServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class ServiceProviderTest extends TestCase
{
    use InteractsWithServiceProvider;

    public function test_merges_config(): void
    {
        static::assertSame(
            $this->app->make('files')->getRequire(TransbankServiceProvider::CONFIG),
            $this->app->make('config')->get('transbank')
        );
    }

    public function test_loads_views(): void
    {
        $this->assertHasViews(TransbankServiceProvider::VIEWS, 'transbank');
    }

    public function test_registers_http_client(): void
    {
        static::assertTrue($this->app->bound(Client::class));
    }

    public function test_registers_webpay(): void
    {
        $this->assertHasServices(Webpay::class, Oneclick::class);
    }

    public function test_registers_middleware_alias(): void
    {
        $this->assertHasMiddlewareAlias('transbank.protect', ProtectTransaction::class);
    }

    public function test_doesnt_registers_listener_when_protection_is_disabled_by_default(): void
    {
        $listeners = $this->app->make('events')->getRawListeners(TransactionCreated::class);

        static::assertArrayNotHasKey(TransactionCreated::class, $listeners);
    }

    protected function enablesProtection($app)
    {
        $app->make('config')->set('transbank.protect.enabled', true);
    }

    #[DefineEnvironment('enablesProtection')]
    public function test_registers_listener_when_protection_is_enabled(): void
    {
        $this->assertHasListeners(TransactionCreated::class, SaveTransactionToken::class);
    }

    public function test_publishes_config(): void
    {
        static::assertSame([
            TransbankServiceProvider::CONFIG => $this->app->configPath('transbank.php'),
        ], ServiceProvider::pathsToPublish(TransbankServiceProvider::class, 'config'));
    }

    public function test_publishes_translations(): void
    {
        static::assertSame([
            TransbankServiceProvider::LANG => $this->app->langPath('vendor/transbank'),
        ], ServiceProvider::pathsToPublish(TransbankServiceProvider::class, 'translations'));
    }

    public function test_publishes_views(): void
    {
        static::assertSame([
            TransbankServiceProvider::VIEWS => $this->app->resourcePath('views/vendor/transbank'),
        ], ServiceProvider::pathsToPublish(TransbankServiceProvider::class, 'views'));
    }
}
