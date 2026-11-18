# Webpay integration certification

Transbank's automatic integration certification for Webpay Plus requires nine transaction tokens, one per scenario below, all minted against the **real** integration environment (`webpay3gint.transbank.cl`, commerce `597055555532`, default integration secret). This file is the constant checkpoint: the matrix is fixed, and the results table holds the tokens collected so far for the certification form.

The tokens are harvested by the `laragear-transbank-webpay-integration` skill (`resources/boost/skills/webpay-integration/`), which drives the live form with the proven Pest test in its `references/WebpayTokensTest.php`. Ask your AI to **certify Webpay integration**.

## Certification matrix

Fixed. Do not add, drop or invent scenarios.

| # | Scenario key | How the token is produced | Evidence that must hold |
| --- | --- | --- | --- |
| 1 | `credit-approved` | Full journey with the approved credit card | Commit: `response_code` 0, `status` AUTHORIZED |
| 2 | `credit-installments-approved` | Approved credit journey, 3 cuotas selected before Pagar | Commit: 0 / AUTHORIZED, `installments_number` 3 |
| 3 | `debit-approved` | Full journey with the approved debit card (no expiry/CVV) | Commit: `response_code` 0, `status` AUTHORIZED |
| 4 | `credit-declined` | Full journey with the declined credit card | Commit: `response_code` -1, `status` FAILED — the form shows no rejection |
| 5 | `debit-declined` | Full journey with the declined debit card | Commit: `response_code` -1, `status` FAILED |
| 6 | `cancelled-from-form` | Open the form, click `Anular compra y volver` | Return `TBK_TOKEN` + `TBK_ORDEN_COMPRA` + `TBK_ID_SESION`; the payment is never committed |
| 7 | `partially-refunded` | Approved credit journey, then `Webpay::refund($token, intdiv($total, 2))` | Refund `{"type": "NULLIFIED", "response_code": 0}`; report the payment token |
| 8 | `fully-refunded` | Approved credit journey, then `Webpay::refund($token, $total)` | Bare `{"type": "REVERSED"}` (no `response_code`); report the payment token |
| 9 | `timeout-from-form` | Open the form and idle ~10 minutes until expiry | Tokenless return `TBK_ORDEN_COMPRA` + `TBK_ID_SESION` (no `TBK_TOKEN`); the payment is never marked |

## Proven cards and bank authentication

| Outcome | Number | CVV | Expiry |
| --- | --- | --- | --- |
| Approved credit (VISA) | `4051 8856 0044 6623` | `123` | `12/28` |
| Declined credit (Mastercard) | `5186 0595 5959 0568` | `123` | `12/28` |
| Approved debit (Redcompra) | `4051 8842 3993 7763` | — | — |
| Declined debit | `5186 0085 4123 3829` | — | — |

Bank authentication happens on every journey (test issuer): RUT `11.111.111-1`, clave `123`, option **Aceptar** (`TSY`) + Continuar.

## Results

Latest harvest: **2026-11-18** (full run, all nine scenarios). Tokens can be re-harvested any time; a partial re-run merges by scenario key. Report these token values to Transbank's certification form.

| Scenario | Token | Buy order | Amount | Outcome | Commit | Collected |
| --- | --- | --- | --- | --- | --- | --- |
| `credit-approved` | `01aba2ab9910717612e8feaa18f9ce4c41af887884025d12affe03b42be4d573` | `01M7P0ZWR6E1DVQC34QJJZ2639` | 213012 | approved | 0 / AUTHORIZED | 2026-11-18T00:30:42+00:00 |
| `credit-installments-approved` | `01abddf6d48d78280d8c6dca84148f397bdd9ce81462cacb02e7ddbbc268b6b6` | `01M7P2C72WE7ZSF73MQVRSZ3K9` | 118505 | approved | 0 / AUTHORIZED, 3 installments | 2026-11-18T00:54:54+00:00 |
| `debit-approved` | `01ab4ec908ad7085734e54a3df1b3e1dcb571d9c2ec3b1c659f658977a7bc112` | `01M7P2CTS2E72T924JTXV3H9SQ` | 177709 | approved | 0 / AUTHORIZED | 2026-11-18T00:55:13+00:00 |
| `credit-declined` | `01ab2b79eb3c93788c87c8e593514a00258b89feccd96d78b261bf1ede8bc605` | `01M7P2EVCSEAG9BFP3G2D6SZFK` | 131555 | declined | -1 / FAILED | 2026-11-18T00:56:22+00:00 |
| `debit-declined` | `01abccfe91d90562451c02f7c3b709a770e9279e51156a4792634507f834f270` | `01M7P2FQSAE6MRBDHKNVTCCZ8G` | 124310 | declined | -1 / FAILED | 2026-11-18T00:56:49+00:00 |
| `cancelled-from-form` | `01ab36aa8c4268e79ba2e3473717d762b07c8ff178f238e207ca6e4593d7bf8b` | `01M7P0WBG6ED496PH5266267G5` | 139171 | cancelled | never committed; returned `TBK_TOKEN` + `TBK_ORDEN_COMPRA` + `TBK_ID_SESION` | 2026-11-18T00:28:38+00:00 |
| `partially-refunded` | `01ab83030f05dbc43a2239af73d617ba5216272457b9e14883a65d8b570b67cb` | `01M7P2J3H2E26SZGP3SKZ51EBR` | 158652 | refunded | payment 0 / AUTHORIZED + refund NULLIFIED, `response_code` 0 | 2026-11-18T00:58:12+00:00 |
| `fully-refunded` | `01abe7cafa9f816cee8f6889e1d4368bcea690ee131978afaa11aac514cef430` | `01M7P2GZ1XE9QS1B17E5BR0XEX` | 260019 | refunded | payment 0 / AUTHORIZED + refund REVERSED (bare) | 2026-11-18T00:57:31+00:00 |
| `timeout-from-form` | `01abef21ee2abeb1bc2a1346591497b9ff8de2879d95d4d6549116462ff61edb` | `01M7NZ6F36E5MRXKD949KSY95V` | — | timeout | never marked; tokenless return after 603 s idle | 2026-11-18T00:09:12+00:00 |

The `timeout-from-form` return is a tokenless GET (`?TBK_ORDEN_COMPRA=…&TBK_ID_SESION=no-session-id`, no `TBK_TOKEN`), observed live at ~601–602 s via the SPA's `timeoutForm` poll.
