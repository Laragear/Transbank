<?php

namespace Laragear\Transbank;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Route as HttpRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laragear\Transbank\Http\Controllers\FailureRedirectController;

class RouteRedirect
{
    /**
     * Middleware class that verifies CSRF tokens.
     */
    public static $csrfMiddleware = VerifyCsrfToken::class;

    /**
     * Returns a redirection route for failed transactions
     */
    public static function as(string $path, string $route = null, int $status = 303): HttpRoute
    {
        return Route::post($path, FailureRedirectController::class)
            ->defaults('destination', $route ?? $path)
            ->defaults('status', $status)
            ->withoutMiddleware([ShareErrorsFromSession::class, StartSession::class, static::$csrfMiddleware]);
    }
}
