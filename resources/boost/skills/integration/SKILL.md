---
name: laragear-transbank-integration
description: "Produce the Webpay Plus transaction tokens Transbank requires for its automatic integration certification, for any commerce code. Use when producing or refreshing Webpay Plus tokens for a Transbank integration analysis, debugging why such an analysis failed, or preparing certification evidence. Don't use for routine transaction processing or OneClick/Maestro work."
---

# Webpay Plus Token Harvest

This skill produces the transaction tokens that Transbank's automatic integration analysis requires. It drives **real** transactions against the Transbank integration environment, records every token before the form is driven, and outputs a single regenerable table keyed per scenario so individual scenarios can be re-run and merged.

## Credential contract

The skill **never** assumes a commerce code or API secret. On invocation, it obtains them in this order:

1.  **Ask the user** for the Webpay Plus commerce code (`WEBPAY_KEY`).
2.  If the user does not have them at hand, **search the connected email** (if an email integration is available) for the credentials message Transbank sends when a commerce code is granted (sender domain `transbank.cl`; contains the commerce code and integration secret).
3.  If neither works, stop and instruct the user to **request integration credentials from Transbank**, then re-run.

These values are per-invocation inputs (environment variables or parameters). **Never write them to disk.**

`WEBPAY_SECRET` is `Laragear\Transbank\Transbank::INTEGRATION_SECRET`.

## Workflow

### 1. Resolve credentials & verify reachability

Set the credentials in the Laravel config for the current invocation:

```php
config([
    'transbank.environment' => 'integration',
    'transbank.credentials.webpay.key' => $commerceCode,
    'transbank.credentials.webpay.secret' => \Laragear\Transbank\Transbank::INTEGRATION_SECRET,
]);
```

Verify reachability with a minimal transaction create:

```php
$response = \Laragear\Transbank\Facades\Webpay::create(
    buyOrder: 'reachability',
    amount: 100,
    returnUrl: 'https://localhost/return'
);
```

- If this throws `NetworkException`, the integration host is unreachable — stop and report.
- If it throws `ClientException` (e.g., 422 for `buy_order` length), the host is reachable but the request body is wrong; proceed to fix.

### 2. Define the scenario matrix

For the report table, each row is a scenario. 9 are required. The default matrix covers approved credit, declined credit, approved debit, and declined debit. The user may add rows (e.g., more refund scenarios), but these 9 are mandatory.

| Scenario key | Card type | Card number | CVV | Installments | Expiry | Expected outcome |
| --- | --- | --- | --- | --- | --- | --- |
| `approved-credit` | VISA | `4051 8856 0044 6623` | `123` | 0| any future | Approved |
| `approved-cuotas` | Mastercard | `5186 0595 5959 0568` | `123` | 1~6| any future | Approved |
| `approved-debit` | Redcompra | `4051 8842 3993 7763` | — | —| — | Approved |
| `declined-credit` | Mastercard | `5186 0595 5959 0568` | `123` | 0| any future | Declined |
| `declined-debit` | Redcompra | `5186 0085 4123 3829` | —| —| — | Declined |
| `canceled` | — | — | —| —| — | Canceled |
| `refund-partial` | VISA | `4051 8856 0044 6623` | `123` | 0| any future| Partially refunded |
| `refund-full` | VISA | `4051 8856 0044 6623` | `123` | 0| any future| Fully refunded |
| `timeout` | — | — | —| —| — | Timeout |

- **Refunds:** You're required to create an approved payment, save that token, apply a refund, and report that token once the refund is approved.
- **Timeout:** The timeout redirects the user from the Transbank form back to the application.

### 3. Create transactions & record tokens

For each scenario, create a real transaction. **`buy_order` must be ≤ 26 characters** — longer orders get HTTP 422.

```php
$buyOrder = $scenarioKey . '-' . now()->format('His'); // Keep under 26 chars

$amount = 1500; // Example amount

$response = \Laragear\Transbank\Facades\Webpay::create(
    buyOrder: $buyOrder,
    amount: $amount,
    returnUrl: $returnUrl
);

$token = $response->getToken();
$url = $response->getUrl();
```

Record the token **before** driving the form. This is the only way to capture tokens for each operation.

### 4. Drive the hosted form

Use a browser automation tool (Playwright, Puppeteer, etc.) to drive the hosted form at `$url`. Follow the journey encoded in the reference section below.

- **Serial execution only.** Do not drive multiple forms concurrently — Transbank sessions are tied to a single browser context.
- **Bounded runtimes:** Each scenario has a hard timeout of **30 seconds** from form navigation to return URL callback. If the browser does not return within this window, mark the scenario as wedged and proceed to recovery.
- **Wedged-browser recovery:** If a scenario wedges:
  1. Kill the browser driver.
  2. Retry the scenario **once**.
  3. If it wedges again, mark it as failed and move on.

### 5. Read the outcome

After the browser returns to the commerce return URL, read the outcome from `Webpay::commit()`, not from the form UI:

```php
$transaction = \Laragear\Transbank\Facades\Webpay::commit($token);

$outcome = $transaction->isSuccessful() ? 'approved' : 'declined';
$responseCode = $transaction->get('response_code');
$status = $transaction->get('status');
```

- **Approved:** `response_code` = `0`, `status` = `AUTHORIZED`.
- **Declined:** `response_code` = `-1`, `status` = `FAILED`. The form shows **no** rejection UI — only `commit()` reveals the decline.
- **Cancelled** (user clicked "Anular compra y volver"): The return URL contains `TBK_TOKEN`, `TBK_ORDEN_COMPRA`, and `TBK_ID_SESION`. Do not commit these; record as cancelled.
- **Expired** (form lifetime exceeded): Tokenless GET to return URL with only `TBK_ORDEN_COMPRA` and `TBK_ID_SESION` (no `TBK_TOKEN`, no `token_ws`). This fails `WebpayRequest` validation (`token_ws required_without_all`) when validation is enabled, so `commit()` is never reached. Record as expired.

### 6. Refund scenarios

For same-day refunds on approved transactions:

```php
$refund = \Laragear\Transbank\Facades\Webpay::refund($token, $amount);
```

- **Partial refund:** Returns `{"type": "NULLIFIED", "response_code": 0, ...}`.
- **Full refund:** Returns bare `{"type": "REVERSED"}` (no `response_code`). Both count as successful via `$refund->isSuccessful()`.

### 7. Produce the deliverable

Output one table with these columns:

| Scenario | Token | Buy Order | Amount | Card | Outcome | Response Code | Status | Timestamp |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |

Key each row by `scenario_key` so single scenarios can be re-run and merged. When re-running a single scenario, load the existing table, replace only the matching row, and write back.

## Encoding rules

- **Serial execution only.** Never parallelize form driving.
- **Bounded runtimes.** 120-second hard timeout per scenario.
- **Wedged-browser recovery.** Kill browser, retry once, then skip.
- **Skip-by-default.** This skill only runs during explicit harvest invocations. Never trigger it from routine package usage.
- **No credential persistence.** Credentials are in-memory only.

## Reference: live-verified Transbank integration environment findings

### Flow (identical for credit and debit)

→ Form SPA bootstraps ~2.5 s 
→ payment-method tile "Tarjetas Crédito, Débito, Prepago" 
→ card form (card input applies a mask; typing the card number then pressing **Tab** reveals the expiry and CVV inputs — the tab-index is broken) 
→ cuotas control is a **collapsed dropdown** (open it, then click the option; options are hidden in the DOM until opened) 
→ "Pagar" → issuer/bank authentication page (RUT `11.111.111-1`, clave `123`, then a `<select>` with **Aceptar** = value `TSY` + **Continuar**) 
→ auto-advancing interstitials (~3–5 s each)
→ GET back to the return URL.

Debit cards need neither expiry nor CVV. There is no voucher page in the REST flow. Full approved journey ≈ 15–25 s.

### Outcome shapes

- **Approved and declined** both return `GET {returnUrl}?token_ws=…` — the form shows **no rejection UI**; only `commit()` reveals a decline (`response_code` −1, `status` FAILED).
- **Cancel** ("Anular compra y volver", present on every page, no confirmation) returns `GET {returnUrl}?TBK_TOKEN=…&TBK_ORDEN_COMPRA=…&TBK_ID_SESION=…`.
- **Form lifetime is 10 minutes:** the SPA polls `…/services/revision/timeoutForm` at ~601 s and auto-redirects at ~602 s via a **tokenless GET** `{returnUrl}?TBK_ORDEN_COMPRA=…&TBK_ID_SESION=…` (no `TBK_TOKEN`, no `token_ws`). Note for the package: a tokenless GET fails `WebpayRequest`'s validation rules (`token_ws required_without_all:…`) whenever validation is enabled, so `commit()` is never reached — timeout is *not* param-identical to abort.
- **Same-day refunds:** partial → `{"type": "NULLIFIED", "response_code": 0, …}`; full → bare `{"type": "REVERSED"}` (no `response_code`). Both must count as successful.
- **No rate limiting** observed at the API or form level (~25 transactions + form navigations in a few hours from one IP).

### Automation quirks

- Single top-level page, no input iframes.
- A hidden duplicate of the cancel button exists inside an `app-modal-protagonismo-correo` component — always click the first *visible* match.
- Multi-element texts (the method tile spans two elements) require whitespace-normalized text matching.
- Page-content waits should allow ≥ 30 s for slow hydration of the commerce return page.

### Package API surface

| Method | Signature | Returns |
| --- | --- | --- |
| Create | `Webpay::create(string $buyOrder, int\|float $amount, string $returnUrl)` | `Transactions\Response` (holds `getToken()`, `getUrl()`) |
| Commit | `Webpay::commit(string $token)` | `Transactions\Transaction` |
| Status | `Webpay::status(string $token)` | `Transactions\Transaction` |
| Refund | `Webpay::refund(string $token, int\|float $amount)` | `Transactions\Transaction` |

### Configuration keys

| Key | Purpose |
| --- | --- |
| `transbank.environment` | `'integration'` or `'production'` |
| `transbank.credentials.webpay.key` | Commerce code |
| `transbank.credentials.webpay.secret` | API secret |
| `transbank.http.timeout` | Request timeout in seconds (default: 10) |
| `transbank.http.retries` | Retry count for non-idempotent-safe requests (default: 3) |

### Transaction status constants

| Constant | Value |
| --- | --- |
| `AUTHORIZED` | Payment approved |
| `NULLIFIED` | Partial refund |
| `REVERSED` | Full refund |
| `PARTIALLY_NULLIFIED` | Partially refunded |
| `CAPTURED` | Capture confirmed |
| `FAILED` | Transaction failed/declined |
