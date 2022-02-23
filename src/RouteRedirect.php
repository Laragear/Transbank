<?php

namespace Laragear\Transbank;

use Illuminate\Routing\Route as HttpRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laragear\Transbank\Http\Controllers\FailureRedirectController;

class RouteRedirect
{
    /**
     * Middleware class that verifies CSRF tokens.
     *
     * @var string
     */
    public static $csrfMiddleware = \App\Http\Middleware\VerifyCsrfToken::class;

    /**
     * Returns a redirection route for failed transactions
     *
     * @param  string  $path
     * @param  string|null  $route
     * @return \Illuminate\Routing\Route
     */
    public static function as(string $path, string $route = null, int $status = 303): HttpRoute
    {
        return Route::post($path, FailureRedirectController::class)
            ->defaults('destination', $route ?? $path)
            ->defaults('status', $status)
            ->withoutMiddleware([ShareErrorsFromSession::class, StartSession::class, static::$csrfMiddleware]);
    }
}
