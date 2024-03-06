<?php

namespace Laragear\Transbank;

use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher as EventContract;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laragear\Transbank\Events\TransactionCreated;

class TransbankServiceProvider extends ServiceProvider
{
    public const CONFIG = __DIR__.'/../config/transbank.php';

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/transbank.php', 'transbank');

        $this->app->bind(Services\Webpay::class);
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
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([static::CONFIG => $this->app->configPath('transbank.php')], 'config');
        }
    }
}
