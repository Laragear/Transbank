<?php

namespace Laragear\Transbank;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher as EventContract;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laragear\Transbank\Events\RegistrationStarted;
use Laragear\Transbank\Events\TransactionCreated;
use RuntimeException;

class TransbankServiceProvider extends ServiceProvider
{
    public const string CONFIG = __DIR__.'/../config/transbank.php';
    public const string VIEWS = __DIR__.'/../resources/views';
    public const string LANG = __DIR__.'/../lang';

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/transbank.php', 'transbank');

        $this->loadViewsFrom(static::VIEWS, 'transbank');
        $this->loadTranslationsFrom(static::LANG, 'transbank');

        $this->app->bind(Services\Webpay::class);
        $this->app->bind(Services\Oneclick::class);
        $this->app->bind(Http\Client::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router, ConfigContract $config, EventContract $dispatcher): void
    {
        $router->aliasMiddleware('transbank.protect', Http\Middleware\ProtectTransaction::class);

        if ($config->get('transbank.protect.enabled')) {
            $dispatcher->listen(TransactionCreated::class, Listeners\SaveTransactionToken::class);
            $dispatcher->listen(RegistrationStarted::class, Listeners\SaveTransactionToken::class);
        }

        if ($config->get('transbank.emulator.enabled')) {
            if ($this->app->environment('production')) {
                throw new RuntimeException('Transbank Emulator should be disabled on production.');
            }

            $this->registerEmulator($router, $config);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([static::LANG => $this->app->langPath('vendor/transbank')], 'translations');
            $this->publishes([static::VIEWS => $this->app->resourcePath('views/vendor/transbank')], 'views');
            $this->publishes([static::CONFIG => $this->app->configPath('transbank.php')], 'config');
        }
    }

    /**
     * Register the routes and the fake HTTP responses of the Webpay emulator.
     */
    protected function registerEmulator(Router $router, ConfigContract $config): void
    {
        $this->app->singleton(Http\Emulator\Store::class);

        $path = trim((string) $config->get('transbank.emulator.path', 'transbank/emulator'), '/');

        $router->get($path, [Http\Controllers\EmulatorController::class, 'show'])
            ->name('transbank.emulator.show');
        $router->post($path, [Http\Controllers\EmulatorController::class, 'submit'])
            ->name('transbank.emulator.submit');

        $this->app->booted(function (): void {
            $emulator = $this->app->make(Http\Emulator\FakeResponses::class);

            // The fake stubs only invoke Closures, so the emulator is wrapped in one.
            $this->app->make(HttpFactory::class)->fake([
                Http\Client::PRODUCTION_ENDPOINT.'*' => fn (HttpRequest $request) => $emulator($request),
                Http\Client::INTEGRATION_ENDPOINT.'*' => fn (HttpRequest $request) => $emulator($request),
            ]);
        });
    }
}
