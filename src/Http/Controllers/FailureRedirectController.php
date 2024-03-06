<?php

namespace Laragear\Transbank\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;
use function abort_if;
use function http_build_query;

class FailureRedirectController extends RedirectController
{
    /**
     * Redirects the POST request of a failed transaction from Transbank.
     */
    public function __invoke(Request $request, UrlGenerator $url): RedirectResponse
    {
        $keys = $request->only('token_ws', 'TBK_TOKEN', 'TBK_ID_SESSION', 'TBK_ORDEN_COMPRA');

        abort_if(empty($keys), 404);

        $redirect = parent::__invoke($request, $url);

        $redirect->setTargetUrl(
            Str::of($redirect->getTargetUrl())->afterLast('?')->finish('?')->append(http_build_query($keys))
        );

        return $redirect;
    }
}
