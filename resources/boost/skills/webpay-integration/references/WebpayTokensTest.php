<?php

/**
 * Real Transbank integration (Webpay Plus) certification-token harvest.
 *
 * Proven end-to-end against the LIVE integration form at
 * `webpay3gint.transbank.cl` (commerce `597055555532`). These tests are NOT
 * part of hermetic CI: they drive real transactions and skip by default.
 * Run them ONLY as a direct serial Pest invocation with the emulator forced
 * off (phpunit.xml would otherwise enable it):
 *
 *   XDEBUG_MODE=off PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/home/sail/php-conf.d WEBPAY_INTEGRATION=true TRANSBANK_EMULATOR=false timeout 1200 vendor/bin/pest tests/Browser/Integration/WebpayTokensTest.php
 *
 * (serial, never --parallel; timeout-only re-run: same env prefix with
 * `timeout 900 vendor/bin/pest tests/Browser/Integration/WebpayTokensTest.php --filter=timeout`).
 *
 * Each scenario records the verbatim Webpay token + context into
 * tests/Browser/Integration/tokens.json; the afterAll hook renders
 * TRANSACTION_TOKENS.md at the application base path. The certification
 * matrix and the current token ledger live in INTEGRATION.md at the
 * project root; the driving knowledge lives in the webpay-integration
 * skill (resources/boost/skills/webpay-integration/SKILL.md).
 *
 * ------------------------------------------------------------------
 * APPLICATION ANCHORS — adapt these when incorporating into an app:
 *   1. `webpayIntegrationCreatePayment()` mirrors the application's
 *      payment-intent creation (models, quote, total and metadata).
 *   2. `WEBPAY_RESULT_PATH` is the application return route Transbank
 *      redirects the user back to.
 *   3. `WEBPAY_TEXT_SUCCESS` / `WEBPAY_TEXT_FAILURE` are the headlines
 *      the application result page renders for each outcome, and the
 *      timeout scenario asserts where an unvalidated return lands.
 *   4. `test()->openPage()` / `test()->screenshotPage()` are the Pest
 *      browser helpers the application provides (page opening and step
 *      screenshots).
 * Everything else (form driving, waits, ledger, outcomes) is generic.
 * ------------------------------------------------------------------
 */

use App\Billing\Quote;
use App\Models\Payment;
use App\Models\Subscription;
use Laragear\Transbank\Facades\Webpay;
use Laragear\Transbank\Services\Transactions\Transaction;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;

/*
|--------------------------------------------------------------------------
| Application anchors: ADAPT THESE
|--------------------------------------------------------------------------
*/
define('WEBPAY_RESULT_PATH', '/subscription/result/webpay');

define('WEBPAY_TEXT_SUCCESS', 'Pago exitoso');

define('WEBPAY_TEXT_FAILURE', 'Pago fallido');

/*
|--------------------------------------------------------------------------
| Integration gospel: cards, bank authentication, scenario order
|--------------------------------------------------------------------------
*/
define('WEBPAY_CARD_CREDIT_APPROVED', '4051885600446623');
define('WEBPAY_CARD_CREDIT_APPROVED_CVV', '123');
define('WEBPAY_CARD_CREDIT_APPROVED_EXPIRY', '12/28');

define('WEBPAY_CARD_CREDIT_DECLINED', '5186059559590568');
define('WEBPAY_CARD_CREDIT_DECLINED_CVV', '123');
define('WEBPAY_CARD_CREDIT_DECLINED_EXPIRY', '12/28');

define('WEBPAY_CARD_DEBIT_APPROVED', '4051884239937763');
define('WEBPAY_CARD_DEBIT_DECLINED', '5186008541233829');

define('WEBPAY_BANK_RUT', '11.111.111-1');
define('WEBPAY_BANK_CLAVE', '123');

/**
 * Scenario keys in the order they must appear in tokens.json and the
 * rendered TRANSACTION_TOKENS.md.
 */
define('WEBPAY_TOKEN_SCENARIOS', [
    'credit-approved',
    'credit-installments-approved',
    'debit-approved',
    'credit-declined',
    'debit-declined',
    'cancelled-from-form',
    'partially-refunded',
    'fully-refunded',
    'timeout-from-form',
]);

/**
 * The exact command that runs the live harvest, rendered into the deliverable.
 */
define('WEBPAY_TOKENS_RUN_COMMAND', 'XDEBUG_MODE=off PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/home/sail/php-conf.d '
    .'WEBPAY_INTEGRATION=true TRANSBANK_EMULATOR=false timeout 1200 vendor/bin/pest tests/Browser/Integration/WebpayTokensTest.php');

afterAll(function () {
    webpayTokensRender();
});

beforeEach(function () {
    if (getenv('WEBPAY_INTEGRATION') !== 'true') {
        $this->markTestSkipped('Webpay integration harvest not requested: re-run with WEBPAY_INTEGRATION=true and TRANSBANK_EMULATOR=false — see this file header.');
    }

    expect(config('transbank.emulator.enabled'))->toBeFalse('The Transbank emulator is ENABLED: real-integration tokens are impossible. Re-run with TRANSBANK_EMULATOR=false in the process env (phpunit.xml would otherwise force it on).')
        ->and(config('transbank.environment'))->not->toBe('production');
});

/*
|--------------------------------------------------------------------------
| Tokens ledger: keyed-merge into tokens.json
|--------------------------------------------------------------------------
*/

/**
 * The tokens ledger path: keyed scenario data lives here between runs.
 */
function webpayTokensPath(): string
{
    return __DIR__.'/tokens.json';
}

/**
 * Loads the tokens ledger as a decodable array (never throws on a fresh or
 * corrupted file: a partial re-run just rebuilds its scenarios).
 *
 * @return array<string, mixed>
 */
function webpayTokensLoad(): array
{
    $contents = @file_get_contents(webpayTokensPath());

    if ($contents === false) {
        return [];
    }

    $decoded = json_decode($contents, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Keyed-merges the scenario data into the tokens ledger, so a partial re-run
 * only updates its own scenarios.
 */
function webpayTokensRecord(string $key, array $data): void
{
    $tokens = webpayTokensLoad();

    $tokens[$key] = $data;

    file_put_contents(webpayTokensPath(), json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Keyed-merges an annotation under the `notes` key of the tokens ledger.
 */
function webpayTokensNote(string $key, string $text): void
{
    $tokens = webpayTokensLoad();

    $tokens['notes'][$key] = $text;

    file_put_contents(webpayTokensPath(), json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Remembers (and returns) the return URL given to the latest Webpay::create(),
 * so it is recorded next to the token.
 */
function webpayTokensReturnUrl(?string $set = null): string
{
    static $url = '';

    return $url = $set ?? $url;
}

/**
 * Regenerates TRANSACTION_TOKENS.md at the application base path from the
 * tokens ledger: header (environment, date, re-run command), the proven
 * test-cards table, the annotations section and one section per scenario
 * with the verbatim token. Called from the afterAll hook.
 */
function webpayTokensRender(): void
{
    $tokens = webpayTokensLoad();

    $lines = [
        '# Webpay integration transaction tokens',
        '',
        '- Environment: real Transbank integration (`webpay3gint.transbank.cl`, commerce `597055555532`, emulator OFF)',
        '- Generated: '.now()->toIso8601String(),
        '- Re-run command: `'.WEBPAY_TOKENS_RUN_COMMAND.'` (serial, never `--parallel`; timeout-only re-run: same env prefix with `timeout 900 … --filter=timeout`)',
        '',
        '## Test cards',
        '',
        '| Outcome | Number | CVV | Expiry |',
        '| --- | --- | --- | --- |',
        '| Approved credit (VISA) | `4051 8856 0044 6623` | `123` | `12/28` |',
        '| Declined credit (Mastercard) | `5186 0595 5959 0568` | `123` | `12/28` |',
        '| Approved debit (Redcompra) | `4051 8842 3993 7763` | — | — |',
        '| Declined debit | `5186 0085 4123 3829` | — | — |',
        '',
        'Bank authentication (test issuer, every journey): RUT `11.111.111-1`, clave `123`, option **Aceptar** (`TSY`) + Continuar.',
    ];

    $notes = $tokens['notes'] ?? [];

    if ($notes !== []) {
        $lines[] = '';
        $lines[] = '## Annotations';
        $lines[] = '';

        foreach ($notes as $key => $text) {
            $lines[] = "- **{$key}**: {$text}";
        }
    }

    foreach (WEBPAY_TOKEN_SCENARIOS as $scenario) {
        if (! isset($tokens[$scenario]) || ! is_array($tokens[$scenario])) {
            continue;
        }

        $lines[] = '';
        $lines[] = "## {$scenario}";
        $lines[] = '';

        foreach ($tokens[$scenario] as $field => $value) {
            $lines[] = '- '.str_replace('_', ' ', (string) $field).': `'.(is_array($value) ? json_encode($value) : $value).'`';
        }
    }

    file_put_contents(base_path('TRANSACTION_TOKENS.md'), implode(PHP_EOL, $lines).PHP_EOL);
}

/*
|--------------------------------------------------------------------------
| Payment intent: APPLICATION ANCHOR
|--------------------------------------------------------------------------
*/

/**
 * Mirrors the application's payment-intent creation (`RenewController::
 * createPaymentIntent()`, quote included): builds a Payment for one renewal
 * cycle of a fresh subscription and creates a real Webpay Plus transaction
 * against the integration environment. The `gateway_token` is saved
 * immediately so the Payment is capturable whatever the outcome (declined,
 * cancelled or expired forms never commit).
 *
 * The total and the `items` metadata come from the REAL `Quote::for()`, not a
 * pinned amount: the successful commit dispatches the `Renew` job, whose
 * pipeline bails out with a 500 when the payment lacks `items`
 * (`CheckPaymentHasItems`), and the invoice must carry the quoted items.
 *
 * The return URL anchors to the live in-process server origin (parsed from a
 * page opened with `openPage()`), never to `app.url`.
 *
 * @return array{payment: Payment, token: string, url: string}
 */
function webpayIntegrationCreatePayment(string $origin): array
{
    $subscription = Subscription::factory()->hasReminders(2)->create();

    $quote = Quote::for(
        $subscription->business,
        $subscription->plan,
        $subscription->priceInClp(),
        'Renovación Agenda Digital',
        1,
    );

    /** @noinspection LaravelEloquentGuardedAttributeAssignmentInspection */
    $payment = Payment::make([
        'service' => 'transbank',
        'gateway' => 'webpay',
        'total' => $quote->total(),
        'metadata' => array_filter([
            'subscription_id' => $subscription->getKey(),
            'cycles' => 1,
            ...$quote->metadata(),
        ]),
    ]);

    $payment->business()->associate($subscription->business()->getParentKey());
    $payment->save();

    $response = Webpay::create($payment->ulid(), $payment->total, webpayTokensReturnUrl($origin.WEBPAY_RESULT_PATH));

    // The token identifies the transaction whatever its outcome; persist it
    // before the browser journey, like RenewController does.
    $payment->gateway_token = $response->getToken();
    $payment->save();

    return [
        'payment' => $payment,
        'token' => $response->getToken(),
        'url' => $response->toString(),
    ];
}

/**
 * Extracts the scheme + host + port origin of the page the browser is on.
 * The in-process server origin is only knowable from a live page URL.
 */
function webpayFormOrigin(PendingAwaitablePage|Webpage $page): string
{
    $parts = parse_url($page->url());

    return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
}

/**
 * Opens the application root to learn the live in-process server origin and
 * creates a real Webpay Plus transaction anchored to it.
 *
 * @return array{payment: Payment, token: string, url: string}
 */
function webpayTokensStart(Webpage|PendingAwaitablePage $home): array
{
    return webpayIntegrationCreatePayment(webpayFormOrigin($home));
}

/*
|--------------------------------------------------------------------------
| Form driving: event-driven waits, never sleeps
|--------------------------------------------------------------------------
*/

/**
 * Polls the callback until it returns truthy or the deadline passes. Always
 * event-driven: a 100 ms poll loop, never a sleep.
 */
function webpayFormPoll(callable $callback, int $seconds = 30): mixed
{
    $deadline = microtime(true) + $seconds;

    do {
        $result = $callback();

        if ($result) {
            return $result;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    return $result;
}

/**
 * Fills the Webpay form field by selector (filling by selector works; the
 * form's tab-index is broken) and verifies the value stuck, retrying inside
 * the poll loop because the Angular SPA hydrates late. Both sides of the
 * verification are whitespace-stripped: the card input carries a MASK that
 * reformats `4051885600446623` into `4051 8856 0044 6623`, so an exact
 * equality check can never match (the masked fill itself works).
 */
function webpayFormFill(PendingAwaitablePage|Webpage $page, string $selector, string $value, string $description): void
{
    $selectorJson = json_encode($selector, JSON_THROW_ON_ERROR);
    $expectedJson = json_encode(preg_replace('/\s+/', '', $value), JSON_THROW_ON_ERROR);

    $filled = webpayFormPoll(function () use ($page, $selector, $value, $selectorJson, $expectedJson): bool {
        $page->fill($selector, $value);

        return (bool) $page->script(<<<JS
            (() => (document.querySelector({$selectorJson})?.value ?? '').replace(/\s+/g, '') === {$expectedJson})()
        JS);
    });

    expect($filled)->toBeTrue("The [{$description}] field never kept its value.");
}

/**
 * Waits until the page body contains the given text (the SPA bootstraps
 * slowly) and clicks the CSS/Playwright selector. The innerText is
 * whitespace-normalized before matching: many form texts span SEPARATE
 * elements (the method tile is a "Tarjetas" heading plus a "Crédito,
 * Débito, Prepago" subtitle), so the raw innerText carries a newline
 * between them.
 */
function webpayFormClick(PendingAwaitablePage|Webpage $page, string $selector, string $text, string $description): void
{
    $textJson = json_encode($text, JSON_THROW_ON_ERROR);

    $found = webpayFormPoll(fn (): bool => (bool) $page->script(
        "(() => document.body.innerText.replace(/\\s+/g, ' ').includes({$textJson}))()"
    ));

    expect($found)->toBeTrue("The [{$description}] never appeared on the page.");

    test()->screenshotPage($page, $description);

    $page->click($selector);
}

/**
 * Drives the full Webpay form journey up to the commerce return: method tile
 * → card → Tab reveal → expiry/CVV (credit only) → cuotas button (when
 * requested) → Pagar → bank authentication (RUT + clave, Aceptar) → poll
 * until the browser lands back on the application result page.
 *
 * Debit cards need neither expiry nor CVV (leave both empty). Cuotas are a
 * COLLAPSED DROPDOWN, not a select: `$cuotas = 3` opens it and picks the
 * "3 cuotas" option; 0 keeps the default "Sin Cuotas".
 */
function webpayFormPay(PendingAwaitablePage|Webpage $page, string $card, string $cvv = '', string $expiry = '', int $cuotas = 0): void
{
    $path = fn (): string => (string) parse_url($page->url(), PHP_URL_PATH);

    // Method selection (~2.5 s SPA bootstrap).
    webpayFormClick(
        $page,
        'text=Tarjetas Crédito, Débito, Prepago',
        'Tarjetas Crédito, Débito, Prepago',
        'method selection tile',
    );

    // Card number.
    webpayFormFill($page, 'input[id*="card" i]', $card, 'card number');
    test()->screenshotPage($page, 'card-filled');

    // Tab in the card field reveals the expiry + CVV inputs.
    $page->keys('input[id*="card" i]', 'Tab');

    if ($expiry !== '') {
        webpayFormFill($page, 'input[autocomplete="cc-exp"]', $expiry, 'expiry');
    }

    if ($cvv !== '') {
        webpayFormFill($page, 'input[id*="cvv" i]', $cvv, 'CVV');
    }

    if ($cuotas > 0) {
        webpayFormSelectCuotas($page, $cuotas);
    }

    test()->screenshotPage($page, 'form-complete');

    // Pay and wait for the test-issuer authentication page (every journey).
    $page->click('button:has-text("Pagar")');

    $authenticating = webpayFormPoll(fn (): bool => str_contains($page->url(), 'authenticator.cgi'));

    expect($authenticating)->toBeTrue('The form never reached the bank authentication page after paying.');
    test()->screenshotPage($page, 'bank-auth');

    webpayFormFill($page, 'input[name*="ut" i]', WEBPAY_BANK_RUT, 'bank RUT');
    webpayFormFill($page, 'input[type="password"]', WEBPAY_BANK_CLAVE, 'bank clave');
    $page->click('input[type="submit"]');

    // Accept step: a plain <select> (Aceptar = TSY) + Continuar submit. The
    // page ships no JavaScript, so the DOM value is exactly what submits
    // (same discipline as the emulator selects).
    $processing = webpayFormPoll(function () use ($page): bool {
        $page->script(<<<'JS'
            (() => {
                const accept = document.querySelector('select');

                if (accept) {
                    accept.value = 'TSY';
                    accept.dispatchEvent(new Event('change', { bubbles: true }));
                }
            })()
        JS);

        return str_contains($page->url(), 'authenticatorProcess.cgi')
            && (bool) $page->script("(() => document.querySelector('select')?.value === 'TSY')()");
    });

    expect($processing)->toBeTrue('The bank never showed the Aceptar/Rechazar accept step.');
    test()->screenshotPage($page, 'bank-accept');

    $page->click('input[value="Continuar"]');

    // Interstitials (bp_control/auth_emisor) auto-advance; just poll the URL.
    $returned = webpayFormPoll(fn (): bool => str_starts_with($path(), WEBPAY_RESULT_PATH));

    expect($returned)->toBeTrue('The browser never returned from Webpay to the application result page.');
}

/**
 * Selects installments on the cuotas control. The control renders as a
 * COLLAPSED DROPDOWN displaying "Sin Cuotas": the option buttons ("3 cuotas",
 * …) exist in the DOM but stay HIDDEN until the control opens, so text waits
 * never see them. The flow is therefore: open the control by clicking the
 * visible "Sin Cuotas" element (preferring a button/clickable match, since a
 * label may duplicate the text), click the visible option once it renders,
 * and verify the collapsed control now displays the picked option.
 */
function webpayFormSelectCuotas(PendingAwaitablePage|Webpage $page, int $cuotas): void
{
    test()->screenshotPage($page, 'cuotas-closed');

    $option = "{$cuotas} cuotas";

    $opened = webpayFormPoll(function () use ($page): bool {
        $clicked = (bool) $page->script(<<<'JS'
            (() => {
                const matches = [...document.querySelectorAll('button, [role="button"], div, span, label')]
                    .filter((element) => element.offsetParent !== null)
                    .filter((element) => element.textContent.trim() === 'Sin Cuotas');

                if (matches.length === 0) {
                    return false;
                }

                (matches.find((element) => element.tagName === 'BUTTON') ?? matches[0]).click();

                return true;
            })()
        JS);

        return $clicked;
    });

    expect($opened)->toBeTrue('The collapsed cuotas control never showed "Sin Cuotas".');

    // Click the visible option exactly once, then verify the control
    // collapsed showing it: the visible text must carry the picked option
    // and must have dropped "Sin Cuotas".
    $optionJson = json_encode($option, JSON_THROW_ON_ERROR);

    $selected = webpayFormPoll(fn (): bool => (bool) $page->script(<<<JS
        (() => {
            const option = [...document.querySelectorAll('button, [role="button"], div, span, label')]
                .filter((element) => element.offsetParent !== null)
                .find((element) => element.textContent.trim() === {$optionJson});

            if (! option) {
                return false;
            }

            option.click();

            return true;
        })()
    JS));

    expect($selected)->toBeTrue("The visible [{$option}] option never rendered after opening the cuotas control.");

    $kept = webpayFormPoll(fn (): bool => (bool) $page->script(<<<JS
        (() => {
            const text = document.body.innerText.replace(/\s+/g, ' ');

            return text.includes({$optionJson}) && ! text.includes('Sin Cuotas');
        })()
    JS));

    expect($kept)->toBeTrue("The cuotas control never kept [{$option}] after collapsing.");

    test()->screenshotPage($page, 'cuotas-selected');
}

/**
 * Cancels the transaction from the form ("Anular compra y volver" exists on
 * every SPA page, no confirmation) and waits for the TBK_* return on the
 * application result page.
 *
 * A HIDDEN duplicate of the cancel button lives inside the
 * `app-modal-protagonismo-correo` Angular modal (instantiated in the DOM a
 * few seconds after load), so a text locator resolves to two elements and
 * dies on strict mode. The button is therefore clicked via script — the
 * FIRST VISIBLE match only, and exactly ONCE: clicking it repeatedly while
 * the redirect is in flight makes the SPA error out with "Tu transacción no
 * se pudo llevar a cabo" instead of returning. The URL poll carries the rest.
 */
function webpayFormCancel(PendingAwaitablePage|Webpage $page): void
{
    $path = fn (): string => (string) parse_url($page->url(), PHP_URL_PATH);

    $clicked = webpayFormPoll(fn (): bool => (bool) $page->script(<<<'JS'
        (() => {
            const cancel = [...document.querySelectorAll('button')]
                .filter((button) => button.offsetParent !== null)
                .find((button) => button.textContent.trim().includes('Anular compra y volver'));

            if (! cancel) {
                return false;
            }

            cancel.click();

            return true;
        })()
    JS));

    expect($clicked)->toBeTrue('The visible cancel control never appeared on the Webpay form.');

    test()->screenshotPage($page, 'cancel control');

    $left = webpayFormPoll(fn (): bool => ! str_contains((string) parse_url($page->url(), PHP_URL_HOST), 'transbank.cl'));

    expect($left)->toBeTrue('Clicking the visible cancel control never left the Webpay form.');

    $returned = webpayFormPoll(fn (): bool => str_starts_with($path(), WEBPAY_RESULT_PATH));

    expect($returned)->toBeTrue('Cancelling never returned the browser to the application result page.');
}

/**
 * Idles on the form until the 10-minute expiry auto-returns the browser
 * (the timeout scenario). At ~601 s the SPA polls `timeoutForm` and at
 * ~602 s redirects via a TOKENLESS GET `?TBK_ORDEN_COMPRA=…&TBK_ID_SESION=…`
 * to the commerce return, where the app's validation immediately 302s to the
 * subscription finder — the transient result URL cannot be reliably sampled,
 * so the poll waits for the host to LEAVE transbank.cl instead. Returns the
 * elapsed seconds.
 */
function webpayFormAwaitAutoReturn(PendingAwaitablePage|Webpage $page, int $seconds = 720): int
{
    $startedAt = microtime(true);
    $lastUrl = '';

    $returned = webpayFormPoll(function () use ($page, &$lastUrl): bool {
        $lastUrl = $page->url();

        return ! str_contains((string) parse_url($lastUrl, PHP_URL_HOST), 'transbank.cl');
    }, $seconds);

    $elapsed = (int) ceil(microtime(true) - $startedAt);

    expect($returned)->toBeTrue("The expired form never left transbank.cl within [{$seconds}] seconds (last URL: {$lastUrl}).");

    return $elapsed;
}

/**
 * Asserts the text is visible on the result page, polling the page content
 * up to 30 s first (the Inertia shell has been observed staying un-hydrated
 * — a bare "Laravel" body — well past the plugin's assertSee retry window),
 * with a self-describing failure message carrying hydration diagnostics.
 */
function webpayFormSee(PendingAwaitablePage|Webpage $page, string $text): void
{
    $seen = webpayFormPoll(fn (): bool => str_contains((string) $page->content(), $text), 30);

    expect($seen)->toBeTrue(sprintf(
        'Never saw [%s] on %s within 30 s (hydration: %s).',
        $text,
        $page->url(),
        webpayFormDiagnostics($page),
    ));
}

/**
 * Best-effort hydration diagnostics for webpayFormSee failure messages:
 * title, the Inertia data-page attribute snippet, body HTML size and the
 * number of /build/ resources the browser actually fetched. Any probe error
 * degrades to '?' — the page may be wedged mid-navigation while we report.
 */
function webpayFormDiagnostics(PendingAwaitablePage|Webpage $page): string
{
    try {
        return (string) $page->script(<<<'JS'
            (() => {
                const root = document.querySelector('#app, [data-page]');

                const resources = performance.getEntriesByType('resource');

                return JSON.stringify({
                    title: document.title,
                    url: location.href,
                    dataPage: (root?.getAttribute('data-page') ?? '').slice(0, 300),
                    bodyLength: document.body.innerHTML.length,
                    buildResources: resources.filter((entry) => entry.name.includes('/build/')).length,
                });
            })()
        JS);
    } catch (Throwable) {
        return '?';
    }
}

/*
|--------------------------------------------------------------------------
| Scenario records & assertions
|--------------------------------------------------------------------------
*/

/**
 * Parses the query parameter names the browser landed with on the app
 * result page (token_ws for approved/declined, TBK_* for cancel/timeout).
 *
 * @return list<string>
 */
function webpayTokensQuery(Webpage|PendingAwaitablePage $page): array
{
    parse_str((string) parse_url($page->url(), PHP_URL_QUERY), $query);

    return array_keys($query);
}

/**
 * The commit context worth recording for a scenario, straight from the
 * Payment's stored response.
 *
 * @return array<string, mixed>
 */
function webpayTokensResponseData(Payment $payment): array
{
    $data = [
        'response_code' => $payment->response->get('response_code'),
        'status' => $payment->response->get('status'),
        'type' => $payment->response->get('type'),
    ];

    if (filled($payment->response->get('installments_number'))) {
        $data['installments_number'] = $payment->response->get('installments_number');
    }

    return $data;
}

/**
 * The common scenario record: token + buy order + amount + card last4 +
 * outcome + commit context + timestamp.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function webpayTokensData(string $token, Payment $payment, string $card, string $outcome, array $extra = []): array
{
    return [
        'token' => $token,
        'buy_order' => $payment->ulid(),
        'amount' => $payment->total,
        'card_last4' => substr($card, -4),
        'outcome' => $outcome,
        ...webpayTokensResponseData($payment),
        'return_url' => webpayTokensReturnUrl(),
        'timestamp' => now()->toIso8601String(),
        ...$extra,
    ];
}

/**
 * Runs one full approved-shape journey: creates the payment intent, opens
 * the live form, drives it through the bank authentication and waits for the
 * application result page. `$expect` is the result-page headline the app
 * renders for the outcome (the caller asserts the Payment state separately).
 *
 * @return array{Payment, Webpage|PendingAwaitablePage, string}
 */
function webpayTokensJourney(string $card, string $cvv = '', string $expiry = '', int $cuotas = 0, string $expect = WEBPAY_TEXT_SUCCESS): array
{
    $intent = webpayTokensStart(test()->openPage('/'));

    $page = test()->openPage($intent['url']);

    webpayFormPay($page, $card, $cvv, $expiry, $cuotas);

    webpayFormSee($page, $expect);

    return [$intent['payment']->fresh(), $page, $intent['token']];
}

/**
 * Asserts the approved journey left the Payment successful and returns it.
 */
function webpayTokensJourneyAssertApproved(Payment $payment): Payment
{
    expect($payment->successful_at)->not->toBeNull('The approved payment never succeeded.')
        ->and($payment->failed_at)->toBeNull()
        ->and($payment->response->get('response_code'))->toBe(0)
        ->and($payment->response->get('status'))->toBe('AUTHORIZED');

    return $payment;
}

/**
 * Asserts the refund transaction went through (NULLIFIED or REVERSED, both
 * successful).
 */
function webpayTokensAssertRefund(Transaction $refund): void
{
    expect($refund->isSuccessful())->toBeTrue('The refund never succeeded.')
        ->and(in_array($refund->get('type'), [Transaction::STATUS_NULLIFIED, Transaction::STATUS_REVERSED], true))
        ->toBeTrue('The refund type was neither NULLIFIED nor REVERSED.');
}

/**
 * The refund context recorded under the scenario's `refund` key. A same-day
 * full reversal may answer a bare `{"type": "REVERSED"}` with no status.
 *
 * @return array<string, mixed>
 */
function webpayTokensRefundData(Transaction $refund): array
{
    return [
        'response_code' => $refund->get('response_code'),
        'status' => $refund->get('status'),
        'type' => $refund->get('type'),
    ];
}

/*
|--------------------------------------------------------------------------
| The 9 certification scenarios
|--------------------------------------------------------------------------
*/

it('harvests the token of an approved credit payment', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_CREDIT_APPROVED, WEBPAY_CARD_CREDIT_APPROVED_CVV, WEBPAY_CARD_CREDIT_APPROVED_EXPIRY);

    $payment = webpayTokensJourneyAssertApproved($payment);

    webpayTokensRecord('credit-approved', webpayTokensData($token, $payment, WEBPAY_CARD_CREDIT_APPROVED, 'approved'));
});

it('harvests the token of an approved credit payment in 3 installments', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_CREDIT_APPROVED, WEBPAY_CARD_CREDIT_APPROVED_CVV, WEBPAY_CARD_CREDIT_APPROVED_EXPIRY, 3);

    $payment = webpayTokensJourneyAssertApproved($payment);

    expect($payment->response->get('installments_number'))->toBe(3, 'The commit never reported 3 installments.');

    webpayTokensRecord('credit-installments-approved', webpayTokensData($token, $payment, WEBPAY_CARD_CREDIT_APPROVED, 'approved'));
});

it('harvests the token of an approved debit payment', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_DEBIT_APPROVED);

    $payment = webpayTokensJourneyAssertApproved($payment);

    webpayTokensRecord('debit-approved', webpayTokensData($token, $payment, WEBPAY_CARD_DEBIT_APPROVED, 'approved'));
});

it('harvests the token of a declined credit payment', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_CREDIT_DECLINED, WEBPAY_CARD_CREDIT_DECLINED_CVV, WEBPAY_CARD_CREDIT_DECLINED_EXPIRY, expect: WEBPAY_TEXT_FAILURE);

    // The form shows no rejection: the commit is what reveals it.
    expect($payment->failed_at)->not->toBeNull('The declined payment never failed.')
        ->and($payment->successful_at)->toBeNull()
        ->and($payment->response->get('response_code'))->toBe(-1)
        ->and($payment->response->get('status'))->toBe('FAILED');

    webpayTokensRecord('credit-declined', webpayTokensData($token, $payment, WEBPAY_CARD_CREDIT_DECLINED, 'declined'));
});

it('harvests the token of a declined debit payment', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_DEBIT_DECLINED, expect: WEBPAY_TEXT_FAILURE);

    expect($payment->failed_at)->not->toBeNull('The declined payment never failed.')
        ->and($payment->successful_at)->toBeNull()
        ->and($payment->response->get('response_code'))->toBe(-1)
        ->and($payment->response->get('status'))->toBe('FAILED');

    webpayTokensRecord('debit-declined', webpayTokensData($token, $payment, WEBPAY_CARD_DEBIT_DECLINED, 'declined'));
});

it('harvests the token of a payment cancelled from the form', function (): void {
    $intent = webpayTokensStart(test()->openPage('/'));

    $page = test()->openPage($intent['url']);

    webpayFormCancel($page);

    // The cancel return arrives within the abandoned window (5 minutes), so
    // the app renders the plain failure page, not the abandoned one.
    webpayFormSee($page, WEBPAY_TEXT_FAILURE);

    $payment = $intent['payment']->fresh();

    expect($payment->failed_at)->not->toBeNull('The cancelled payment never failed.')
        ->and($payment->successful_at)->toBeNull();

    webpayTokensRecord('cancelled-from-form', webpayTokensData($intent['token'], $payment, '', 'cancelled', [
        'return_params' => webpayTokensQuery($page),
    ]));
});

it('harvests the token of a partially refunded payment', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_CREDIT_APPROVED, WEBPAY_CARD_CREDIT_APPROVED_CVV, WEBPAY_CARD_CREDIT_APPROVED_EXPIRY);

    $payment = webpayTokensJourneyAssertApproved($payment);

    $refund = Webpay::refund($payment->gateway_token, intdiv($payment->total, 2));

    webpayTokensAssertRefund($refund);

    webpayTokensRecord('partially-refunded', webpayTokensData($token, $payment, WEBPAY_CARD_CREDIT_APPROVED, 'approved', [
        'refund' => webpayTokensRefundData($refund),
    ]));
});

it('harvests the token of a fully refunded payment', function (): void {
    [$payment, $page, $token] = webpayTokensJourney(WEBPAY_CARD_CREDIT_APPROVED, WEBPAY_CARD_CREDIT_APPROVED_CVV, WEBPAY_CARD_CREDIT_APPROVED_EXPIRY);

    $payment = webpayTokensJourneyAssertApproved($payment);

    $refund = Webpay::refund($payment->gateway_token, $payment->total);

    webpayTokensAssertRefund($refund);

    webpayTokensRecord('fully-refunded', webpayTokensData($token, $payment, WEBPAY_CARD_CREDIT_APPROVED, 'approved', [
        'refund' => webpayTokensRefundData($refund),
    ]));
});

it('harvests the token of a payment abandoned until the form expires', function (): void {
    $intent = webpayTokensStart(test()->openPage('/'));

    $page = test()->openPage($intent['url']);

    test()->screenshotPage($page, 'timeout-form-open');

    $elapsed = webpayFormAwaitAutoReturn($page);

    // The expiry return is a TOKENLESS GET (TBK_ORDEN_COMPRA + TBK_ID_SESION
    // only, no TBK_TOKEN), so the app's WebpayRequest validation fails and
    // 302s to the subscription finder — ResultController never runs and the
    // payment is never marked. That is intended app behavior; assert reality
    // (APPLICATION ANCHOR: the landing path and headline are app-specific).
    expect(parse_url($page->url(), PHP_URL_PATH))->toBe('/subscription/find', 'The expired form never bounced to the subscription finder.');

    webpayFormSee($page, 'Pago rápido');

    $payment = $intent['payment']->fresh();

    expect($payment->successful_at)->toBeNull('The expired payment was unexpectedly marked successful.')
        ->and($payment->failed_at)->toBeNull('The expired payment was unexpectedly marked failed.');

    webpayTokensRecord('timeout-from-form', [
        'token' => $intent['token'],
        'buy_order' => $payment->ulid(),
        'outcome' => 'timeout',
        'elapsed_seconds' => $elapsed,
        'landing_path' => '/subscription/find',
        // The transient query cannot be captured by the page object (the app
        // 302s instantly); this is the verbatim shape probed live.
        'return_url' => webpayTokensReturnUrl(),
        'return_params' => 'TBK_ORDEN_COMPRA='.$payment->ulid().'&TBK_ID_SESION=no-session-id',
        'timestamp' => now()->toIso8601String(),
    ]);

    webpayTokensNote('timeout', sprintf(
        'The form SPA polls timeoutForm at ~601 s and auto-returns at ~602 s (observed after %d s idle) via a tokenless GET '
        .'({returnUrl}?TBK_ORDEN_COMPRA={ulid}&TBK_ID_SESION=no-session-id — no TBK_TOKEN, no token_ws), confirmed by a '
        .'live 10-minute idle probe. The app fails WebpayRequest validation and 302s to the subscription finder, so the '
        .'payment is never marked completed.',
        $elapsed,
    ));
});
