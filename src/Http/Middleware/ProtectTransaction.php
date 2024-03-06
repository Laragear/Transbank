<?php

namespace Laragear\Transbank\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use function abort_unless;
use function array_values;
use function cache;
use function config;

class ProtectTransaction
{
    /**
     * Handle the incoming Transbank POST Request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($this->requestComesFromTransbank($request), 404);

        return $next($request);
    }

    /**
     * Check if the request contains a valid token from Transbank.
     */
    protected function requestComesFromTransbank(Request $request): bool
    {
        if (! $token = $this->token($request)) {
           return false;
        }

        [$enabled, $store, $prefix] = array_values(config('transbank.protect'));

        return !$enabled || cache()->store($store)->pull($prefix.'|'.$token);
    }

    /**
     * Returns the incoming transaction token from Transbank, if any.
     */
    protected function token(Request $request): ?string
    {
        return $request->input('token_ws') ?? $request->input('TBK_TOKEN');
    }
}
