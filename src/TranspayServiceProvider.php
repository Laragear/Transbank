<?php

namespace Laragear\Transbank;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laragear\Transbank\Events\TransactionCreated;

class TranspayServiceProvider extends ServiceProvider
{
    public const CONFIG = __DIR__.'/../config/transbank.php';

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/transbank.php', 'transbank');

        $this->app->bind(Services\Webpay::class);
        $this->app->bind(Http\Client::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $dispatcher
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function boot(Router $router, Repository $config, Dispatcher $dispatcher): void
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
