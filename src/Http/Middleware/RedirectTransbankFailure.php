<?php

namespace Laragear\Transbank\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use function array_filter;
use function http_build_query;
use function redirect;
use function route;
use function str_contains;

class RedirectTransbankFailure
{
    /**
     * Handle the incoming Transbank POST Request.
     */
    public function handle(Request $request, Closure $next, string $route = ''): mixed
    {
        // Intercept the payment gateway hitting your page with a POST request
        // We require this because Transbank may redirect with or without the
        // transaction session token or buy order, especially when aborting.
        if ($request->isMethod('post')) {
            // The POST body is lost on a 303 redirect, so the Transbank keys
            // move into the query string for the follow-up GET request.
            $keys = array_filter($request->only(
                'token_ws',
                'TOKEN_WS',
                'token',
                'TBK_TOKEN',
                'TBK_ID_SESION',
                'TBK_ORDEN_COMPRA',
                'url_webpay',
            ));

            $target = $route ? route($route) : $request->fullUrl();

            return redirect()->to($target.(str_contains($target, '?') ? '&' : '?').http_build_query($keys), 303);
        }

        if ($after = $this->after($request)) {
            return $after;
        }

        return $next($request);
    }

    /**
     * Handles the request after has been normalized.
     *
     * @return mixed|void
     */
    protected function after(Request $request)
    {
        //
    }
}
