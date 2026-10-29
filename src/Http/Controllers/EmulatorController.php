<?php

namespace Laragear\Transbank\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laragear\Transbank\Http\Emulator\Store;
use Laragear\Transbank\Services\Transactions\Response as TransbankResponse;
use function abort_unless;
use function http_build_query;
use function in_array;
use function redirect;
use function str_contains;
use function view;

class EmulatorController
{
    /**
     * Results the developer can select on the emulated page.
     */
    public const array RESULTS = ['approve', 'reject', 'abort', 'fail'];

    /**
     * Token parameter names the return may use, other than the default.
     */
    public const array TOKEN_KEYS = ['token_ws', 'TOKEN_WS', 'token'];

    /**
     * Creates a new EmulatorController instance.
     */
    public function __construct(protected Store $store)
    {
        //
    }

    /**
     * Show the emulated Webpay payment page.
     */
    public function show(Request $request): View
    {
        [$token, $transaction] = $this->transaction($request);

        return view('transbank::emulator.webpay', [
            'token' => $token,
            'transaction' => $transaction,
        ]);
    }

    /**
     * Process the selected result and send the user back to the commerce.
     */
    public function submit(Request $request): RedirectResponse|View
    {
        [$token, $transaction] = $this->transaction($request);

        $transaction['result'] = $this->valid($request->input('result'), self::RESULTS, 'approve');
        $transaction['return_method'] = $this->valid($request->input('method'), ['auto', 'get', 'post'], 'auto');
        $transaction['token_key'] = $this->valid($request->input('token_key'), ['auto', ...self::TOKEN_KEYS], 'auto');
        $transaction['tbk'] = $this->valid($request->input('tbk'), ['auto', 'all', 'none'], 'auto');

        $this->store->put($token, $transaction);

        $params = $this->returnParams($token, $transaction);

        // Transbank only returns by POST in some flaky scenarios, so it can be
        // forced through the optional parameters, using an auto-submitting form.
        if ($transaction['return_method'] === 'post') {
            return view('transbank::emulator.post-return', [
                'url' => $transaction['return_url'],
                'params' => $params,
            ]);
        }

        $separator = str_contains($transaction['return_url'], '?') ? '&' : '?';

        return redirect()->to($transaction['return_url'].$separator.http_build_query($params), 303);
    }

    /**
     * Build the parameters to send back to the commerce return URL.
     */
    protected function returnParams(string $token, array $transaction): array
    {
        $isAborted = in_array($transaction['result'], ['abort', 'fail'], true);

        // Only the "abort" result returns the token as TBK_TOKEN by default;
        // "fail" returns the token as token_ws plus the TBK_* fallbacks.
        $tokenKey = $transaction['token_key'] === 'auto'
            ? ($transaction['result'] === 'abort'
                ? TransbankResponse::ONECLICK_TOKEN
                : TransbankResponse::WEBPAY_TOKEN)
            : $transaction['token_key'];

        $params = [$tokenKey => $token];

        $withTbk = match ($transaction['tbk']) {
            'all' => true,
            'none' => false,
            default => $isAborted,
        };

        if ($withTbk) {
            $params += [
                'TBK_TOKEN' => $token,
                'TBK_ORDEN_COMPRA' => $transaction['buy_order'],
                'TBK_ID_SESION' => $transaction['session_id'],
            ];
        }

        return $params;
    }

    /**
     * Retrieve the emulated transaction by its token, or fail.
     */
    protected function transaction(Request $request): array
    {
        $token = (string) $request->input(TransbankResponse::WEBPAY_TOKEN);

        $transaction = $this->store->get($token);

        abort_unless((bool) $transaction, 404);

        return [$token, $transaction];
    }

    /**
     * Return the input value if it is allowed, or the default.
     */
    protected function valid(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
