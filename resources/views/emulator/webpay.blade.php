<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('transbank::emulator.title') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f6; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1d1d1f; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; }
        .tbk-bar { position: fixed; top: 0; left: 0; right: 0; height: 10px; background: linear-gradient(90deg, #3b1e5c 0%, #b81e5c 45%, #e6437a 60%, #21c3b3 100%); }
        .tbk-logo { font-size: 28px; font-weight: 700; color: #b81e5c; margin: 0 0 24px; }
        .tbk-logo small { display: block; font-size: 11px; font-weight: 400; color: #6b6b70; letter-spacing: .08em; text-transform: uppercase; }
        .tbk-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.08); width: min(420px, 92vw); padding: 28px; }
        .tbk-grid { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        .tbk-label { font-size: 12px; color: #6b6b70; margin: 0 0 4px; }
        .tbk-merchant { font-weight: 700; font-size: 14px; margin: 0; }
        .tbk-amount { font-weight: 800; font-size: 22px; margin: 0; }
        .tbk-select { width: 100%; padding: 12px 14px; border: 1px solid #d9d9de; border-radius: 8px; font-size: 14px; background: #fff; }
        .tbk-options { margin: 14px 0; font-size: 13px; color: #6b6b70; }
        .tbk-options summary { cursor: pointer; margin-bottom: 10px; }
        .tbk-options label { display: block; margin-top: 10px; }
        .tbk-options select { width: 100%; margin-top: 4px; padding: 8px 10px; border: 1px solid #d9d9de; border-radius: 6px; font-size: 13px; background: #fff; }
        .tbk-pay { width: 100%; margin-top: 6px; padding: 13px; border: 0; border-radius: 8px; background: #4b1030; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; }
        .tbk-pay:hover { background: #5c1349; }
        .tbk-order { margin: 14px 0 0; font-size: 12px; color: #6b6b70; text-align: center; }
        .tbk-notice { margin-top: 16px; font-size: 12px; color: #8a8a90; }
    </style>
</head>
<body>
    <div class="tbk-bar"></div>
    <form class="tbk-card" method="POST" action="{{ route('transbank.emulator.submit') }}">
        <input type="hidden" name="token_ws" value="{{ $token }}">
        <p class="tbk-logo">webpay<small>transbank</small></p>
        <div class="tbk-grid">
            <div>
                <p class="tbk-label">{{ __('transbank::emulator.paying_at') }}</p>
                <p class="tbk-merchant">{{ __('transbank::emulator.merchant') }}</p>
            </div>
            <div>
                <p class="tbk-label">{{ __('transbank::emulator.amount') }}</p>
                <p class="tbk-amount">${{ number_format((float) $transaction['amount'], 0, ',', '.') }}</p>
            </div>
        </div>
        <p class="tbk-label">{{ __('transbank::emulator.select_result') }}</p>
        <select class="tbk-select" name="result">
            @foreach(['approve', 'reject', 'abort', 'fail'] as $result)
                <option value="{{ $result }}">{{ __("transbank::emulator.results.{$result}") }}</option>
            @endforeach
        </select>
        <details class="tbk-options">
            <summary>{{ __('transbank::emulator.return_options') }}</summary>
            <label>{{ __('transbank::emulator.method') }}
                <select name="method">
                    <option value="auto">{{ __('transbank::emulator.method_auto') }}</option>
                    <option value="get">GET</option>
                    <option value="post">POST</option>
                </select>
            </label>
            <label>{{ __('transbank::emulator.token_key') }}
                <select name="token_key">
                    <option value="auto">{{ __('transbank::emulator.token_key_auto') }}</option>
                    <option value="token_ws">token_ws</option>
                    <option value="TOKEN_WS">TOKEN_WS</option>
                    <option value="token">token</option>
                </select>
            </label>
            <label>{{ __('transbank::emulator.tbk') }}
                <select name="tbk">
                    <option value="auto">{{ __('transbank::emulator.tbk_auto') }}</option>
                    <option value="all">{{ __('transbank::emulator.tbk_all') }}</option>
                    <option value="none">{{ __('transbank::emulator.tbk_none') }}</option>
                </select>
            </label>
        </details>
        <button class="tbk-pay" type="submit">{{ __('transbank::emulator.pay') }}</button>
        <p class="tbk-order">{{ __('transbank::emulator.buy_order') }}: {{ $transaction['buy_order'] }}</p>
    </form>
    <p class="tbk-notice">{{ __('transbank::emulator.notice') }}</p>
</body>
</html>
