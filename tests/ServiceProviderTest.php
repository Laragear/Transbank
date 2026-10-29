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
use RuntimeException;

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

    public function test_doesnt_register_emulator_routes_by_default(): void
    {
        $routes = $this->app->make('router')->getRoutes();

        static::assertFalse($routes->hasNamedRoute('transbank.emulator.show'));
        static::assertFalse($routes->hasNamedRoute('transbank.emulator.submit'));
    }

    protected function enablesEmulator($app): void
    {
        $app->make('config')->set('transbank.emulator.enabled', true);
    }

    #[DefineEnvironment('enablesEmulator')]
    public function test_registers_emulator_routes_when_enabled(): void
    {
        $routes = $this->app->make('router')->getRoutes();

        static::assertTrue($routes->hasNamedRoute('transbank.emulator.show'));
        static::assertTrue($routes->hasNamedRoute('transbank.emulator.submit'));
    }

    protected function enablesEmulatorInProduction($app): void
    {
        $app['env'] = 'production';
        $app->make('config')->set('app.env', 'production');
    }

    #[DefineEnvironment('enablesEmulatorInProduction')]
    public function test_throws_when_emulator_is_registered_in_production(): void
    {
        $this->app->make('config')->set('transbank.emulator.enabled', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Transbank Emulator should be disabled on production.');

        $this->app->register(new TransbankServiceProvider($this->app), force: true);
    }

    public function test_doesnt_registers_listener_when_protection_is_disabled_by_default(): void
    {
        $listeners = $this->app->make('events')->getRawListeners(TransactionCreated::class);

        static::assertArrayNotHasKey(TransactionCreated::class, $listeners);
    }

    protected function enablesProtection($app): void
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
