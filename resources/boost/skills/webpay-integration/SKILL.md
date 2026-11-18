---
name: laragear-transbank-webpay-integration
description: "Certify Webpay integration: harvest the nine transaction tokens Transbank requires for its automatic Webpay Plus integration certification, driving the real integration form with Pest browser tests inside the application. Use when the user says 'certify Webpay integration', asks to produce, refresh or report certification tokens, or when a token harvest run fails or a scenario wedges. Do not use for routine transaction processing, Oneclick/Maestro work, or the local emulator."
---

# Certify Webpay Integration

Transbank certification for Webpay Plus requires nine transaction tokens, one per scenario, minted against the **real** integration environment (`webpay3gint.transbank.cl`, commerce `597055555532`, the package's default integration credentials). This skill harvests them by driving the live hosted form with a proven Pest reference test, records each token **before and while** the form is driven, and produces one regenerable table the user copy-pastes into Transbank's integration certification form.

## Keeping the certification table

Use `INTEGRATION.md` at the **application root** as the constant checkpoint instead of carrying the certification state in the conversation, because it is the deliverable Transbank certification consumes. The harvest's afterAll hook creates and updates it from the ledger, and it holds **only the table** — one row per harvested scenario, with no headings, explanations, assertions or any other text:

| Scenario | Token | Buy order | Amount | Card | Outcome | Commit | Collected |

- The fixed nine-scenario matrix (how each token is produced, and the evidence each row must hold) is the table in **Gathering each token by scenario** below. Run only those scenarios; never add, drop or invent scenarios, and never report a token the run did not produce and record. Extra tokens are noise.
- After a run, read the table from `INTEGRATION.md` and report it verbatim to the user.

Verification: `INTEGRATION.md` contains only the markdown table, with exactly one row per harvested scenario and no other text.

## Incorporating the proven reference test

Use `references/WebpayTokensTest.php` next to this SKILL.md instead of writing a new harvest, because it is the single self-contained file proven end-to-end against the live form: form driving, event-driven waits, ledger and the nine scenarios all live in it. Place it in the application and adapt what the file marks as `APPLICATION ANCHORS` — the only application-specific parts:

1. `webpayIntegrationCreatePayment()` — mirrors the application's payment-intent creation: the models, quote, total and metadata the application commits on return.
2. `WEBPAY_RESULT_PATH` — the application route Transbank redirects the user back to.
3. `WEBPAY_TEXT_SUCCESS` / `WEBPAY_TEXT_FAILURE` — the headlines the application result page renders; the timeout scenario also asserts where an unvalidated return lands.
4. `test()->openPage()` / `test()->screenshotPage()` — the Pest browser helpers the application provides.

The application needs Pest v4 with browser tests working (in-process server, Playwright).

```bash
cp references/WebpayTokensTest.php tests/Browser/Integration/WebpayTokensTest.php
```

Verification: `php -l` accepts the placed file, and without `WEBPAY_INTEGRATION=true` in the env its guard skips every test instead of driving real transactions.

## Running the harvest

Run the reference test as a direct serial Pest invocation with the emulator forced off, instead of through the application's normal suite, because `phpunit.xml` may force the emulator on and emulated servers cannot produce real tokens. The `PHP_INI_SCAN_DIR` prefix is Sail-specific; keep it while the application runs in Sail.

```bash
XDEBUG_MODE=off PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/home/sail/php-conf.d WEBPAY_INTEGRATION=true TRANSBANK_EMULATOR=false timeout 1200 vendor/bin/pest tests/Browser/Integration/WebpayTokensTest.php
```

- **Serial only, never `--parallel`.** Transbank sessions are tied to a single browser context.
- A single wedged scenario fails its own expectation with diagnostics. Re-run only that scenario, serially: same env prefix with `timeout 900 vendor/bin/pest tests/Browser/Integration/WebpayTokensTest.php --filter=timeout` (substitute the scenario). The ledger keyed-merges, so a partial re-run updates only its own scenario rows.
- Never trigger this skill from routine package usage. It runs on explicit certification requests only.

Verification: `tests/Browser/Integration/tokens.json` gains one key per harvested scenario, and `INTEGRATION.md` renders at the application base path.

## Gathering each token by scenario

Create a real transaction per scenario with `Webpay::create()` and persist the token on the Payment **before** driving the form, instead of reading the token from the return, because declined, cancelled and expired forms never commit and the token must exist independently of the outcome. Keep `buy_order` at 26 characters or fewer (a bare ULID): longer orders get a live HTTP 422. The full journey takes ≈ 15–25 s except the timeout scenario (~10 min).

```php
$response = Webpay::create($buyOrder, $amount, $returnUrl);

// The token identifies the transaction whatever its outcome.
$payment->gateway_token = $response->getToken();
$payment->save();
```

| Scenario key | How the token is produced | What must be recorded |
| --- | --- | --- |
| `credit-approved` | Drive the form with VISA `4051 8856 0044 6623`, CVV `123`, expiry `12/28` | Commit: `response_code` 0, `status` AUTHORIZED |
| `credit-installments-approved` | Same card, then open the cuotas control and pick `3 cuotas` before Pagar | Commit plus `installments_number` 3 |
| `debit-approved` | Drive the form with Redcompra `4051 8842 3993 7763` — no expiry, no CVV | Commit: `response_code` 0, `status` AUTHORIZED |
| `credit-declined` | Drive the form with Mastercard `5186 0595 5959 0568`, CVV `123`, expiry `12/28` | Commit: `response_code` -1, `status` FAILED |
| `debit-declined` | Drive the form with debit `5186 0085 4123 3829` | Commit: `response_code` -1, `status` FAILED |
| `cancelled-from-form` | Open the form and click `Anular compra y volver` on any SPA page | The token plus the `TBK_TOKEN`, `TBK_ORDEN_COMPRA`, `TBK_ID_SESION` return params; the payment is never committed |
| `partially-refunded` | Run an approved credit journey, then `Webpay::refund($token, intdiv($total, 2))` | The payment token plus the refund answer `{"type": "NULLIFIED", "response_code": 0, …}` |
| `fully-refunded` | Run an approved credit journey, then `Webpay::refund($token, $total)` | The payment token plus the bare refund answer `{"type": "REVERSED"}` |
| `timeout-from-form` | Create the transaction, open the form, idle until expiry (~10 min) | The token plus the elapsed seconds and the tokenless return shape; the payment is never marked |

Verification: every recorded scenario holds a 64-character token plus the evidence its matrix row requires, and no extra scenario keys exist in the ledger.

## Driving the live form

Expect one identical journey for credit and debit, instead of per-card-type paths, because the integration form is one Angular SPA: `initTransaction?token_ws=…` auto-POSTs and lands at `webpayserver/dist/#/` → ~2.5 s bootstrap → method tile **"Tarjetas Crédito, Débito, Prepago"** → card form (SPA route `/webpay/tarjetaConNumero`, no page navigation) → **Pagar** → `bp_control.cgi` interstitial (~3 s) → **bank authentication** → `auth_emisor.cgi` interstitial ("Estamos procesando tu pago", ~5 s) → auto-redirect (GET) to the commerce return. There is **no voucher page** in the REST flow.

**Bank authentication happens on EVERY journey, credit included** (the "bank" is Transbank's test issuer, `testcommercebank`): RUT `11.111.111-1` + clave `123` on `authenticator.cgi`, then a plain `<select>` with **Aceptar** (value `TSY`) + **Continuar** on `authenticatorProcess.cgi`. All inputs live in the **top-level document** — the only iframes are GTM/Incapsula helpers, never inputs.

| Step | Selector | Notes |
| --- | --- | --- |
| Method tile | `text=Tarjetas Crédito, Débito, Prepago` | wait ~2.5 s bootstrap; tile text spans two elements |
| Card number | `input[id*="card" i]` | fill 16 digits |
| Expiry (credit only) | `input[autocomplete="cc-exp"]` | does not exist until **Tab** is pressed in the card field |
| CVV (credit only) | `input[id*="cvv" i]` | `123` |
| Cuotas | control showing `Sin Cuotas`; options `3 cuotas`, `6 cuotas`, … | collapsed dropdown, not a `<select>` |
| Pay | `button:has-text("Pagar")` | debit: clickable right after the card number |
| Cancel | `Anular compra y volver` button | on every SPA page; no confirmation dialog |
| Bank RUT | `input[name*="ut" i]` on `authenticator.cgi` | `11.111.111-1` |
| Bank clave | `input[type="password"]`, submit `input[type="submit"]` | `123` |
| Bank accept | `<select>` value `TSY` on `authenticatorProcess.cgi`, then `input[value="Continuar"]` | Rechazar produces a declined commit |
| Return | poll the URL, never the interstitials | interstitials hold 3–5 s and auto-advance |

Quirks the driving code already handles — keep them when adapting:

1. **Tab reveal**: expiry + CVV inputs do not exist until one Tab is pressed in the card field. The tab-index is broken: fill by selector, never tab between fields.
2. **Card mask**: the card input reformats the value with spaces. The fill works, but any verification must compare whitespace-stripped values.
3. **Cuotas options are hidden until the control opens**: click the visible `Sin Cuotas` element (prefer the button match), then the visible option, then verify the control collapsed showing it.
4. **Cancel button has a hidden duplicate** inside the `app-modal-protagonismo-correo` modal, instantiated a few seconds after load: click the **first visible match via script, exactly once**. Repeated clicks while the redirect is in flight make the SPA error out with "Tu transacción no se pudo llevar a cabo".
5. **Multi-element texts** (the method tile) need whitespace-normalized `innerText` waits; a raw `includes` never matches.
6. **Slow Inertia hydration**: the commerce return page may stay an un-hydrated shell well past `assertSee`'s retry window; poll the content up to 30 s before asserting.
7. **Never navigate or click during interstitials** ("no recargues ni cierres la pantalla") — poll the URL instead.
8. **Every wait is a poll loop** (100 ms), never a sleep.

Verification: the browser returns to `WEBPAY_RESULT_PATH` carrying `token_ws`, `TBK_*` or the tokenless timeout GET within the poll deadlines, without any navigation during interstitials.

## Reading outcomes and returns

Read the outcome from `Webpay::commit()` (the application does it on return) instead of the form UI, because **the form shows no rejection page**: a declined journey looks identical to an approved one and still returns `?token_ws=…`. Approved answers `response_code` 0 with `status` AUTHORIZED; declined answers `response_code` -1 with `status` FAILED.

```php
$transaction = Webpay::commit($token);          // approved and declined share this return
$refund = Webpay::refund($token, $amount);      // same-day partial or full refund
```

- **Cancel** (`Anular compra y volver`): `GET {returnUrl}?TBK_TOKEN=…&TBK_ORDEN_COMPRA={buy order}&TBK_ID_SESION=no-session-id`. Never commit these; the payment stays unmarked.
- **10-minute form expiry**: at ~601 s the SPA polls `services/revision/timeoutForm`; at ~602 s it auto-redirects via a **tokenless GET** `{returnUrl}?TBK_ORDEN_COMPRA=…&TBK_ID_SESION=…` — no `TBK_TOKEN`, no `token_ws` (not param-identical to cancel). That tokenless return fails `WebpayRequest` validation when enabled, so the application 302s away and `commit()` is never reached: the payment stays unmarked, both timestamps null.
- **Refunds**: same-day partial → `{"type": "NULLIFIED", "response_code": 0, …}`; same-day full → bare `{"type": "REVERSED"}` with no `response_code`. `Transaction::isSuccessful()` covers both shapes.
- **No rate limits observed** at API or form level (~25 transactions plus navigations from one IP in hours); `buy_order` length is the only known API constraint.

Verification: declines surface only through `commit()`, and both refund answers pass `isSuccessful()` before the scenario is recorded.

## Reporting the deliverable

Report the table from `INTEGRATION.md` at the application root verbatim to the user, instead of leaving the tokens only in the files, because the user copy-pastes these token values into Transbank's integration certification form. The run leaves `tokens.json` (keyed per scenario, regenerable and mergeable) and the `INTEGRATION.md` table at the application base path.

If any scenario failed, report the ones harvested, the exact failure diagnostics of the rest, and offer to re-run only the failed scenarios.

Verification: the reported table has one row per matrix scenario, each with its verbatim token and commit evidence.

## Keeping the harvest bounded

Keep the harvest explicit and bounded instead of opportunistic, because it drives real transactions against Transbank's integration environment.

- Serial execution only; one browser context at a time.
- Every wait is a poll loop, never a sleep; a wedged scenario times out inside its own poll and fails its expectation.
- The harvest runs on explicit certification requests only, never from routine package usage.
- Credentials are the package's default integration pair, held in-memory for the run; nothing is persisted beyond the ledger and reports.

Verification: the run issues no concurrent form sessions, skips itself when `WEBPAY_INTEGRATION` is unset, and the emulator is confirmed off before the first transaction.
