# Wallet (Foodics Pay coding challenge)

Laravel 12 application that **receives** multi-line bank webhooks (Foodics and Acme line formats), normalizes them through **bank adapters**, persists them **idempotently** by `reference` per client, and **builds** standard payment request XML for outbound transfers (generation only; no bank transport).

## Requirements

- PHP 8.2+, Composer
- SQLite (default) or any Laravel-supported database

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

For queued ingestion (recommended outside automated tests):

```bash
php artisan queue:work
```

Configure `QUEUE_CONNECTION` in `.env` (`database` or `redis`).

## Webhook API

`POST /api/webhooks/{bank}/{token}`

- **`bank`**: `foodics` or `acme` (lowercase letters; unknown banks → **422**).
- **`token`**: the client's opaque `webhook_token` (48-char random string, auto-generated on client creation). This replaces a numeric client ID to avoid exposing sequential identifiers.
- **Body**: raw `text/plain` payload; each **non-empty line** is one transaction in that bank's format.

### Foodics line format

`YYYYMMDD` + `amount` (integer part + `,` + two decimals) + `#` + `reference` + `#` + optional key/value tail (`key/value` segments separated by `/`).

Example:

```text
20250615156,50#202506159000001#note/debt payment march/internal_reference/A462JE81
```

### Acme line format

`amount//reference//YYYYMMDD` (amount uses `,` as decimal separator).

Example:

```text
156,50//202506159000001//20250615
```

### Idempotency

`transactions` enforces a **unique** `(client_id, bank, reference)` constraint at the database level. The import job uses `Transaction::create` inside a `try/catch` for `UniqueConstraintViolationException`; duplicate lines in a webhook or across repeated webhooks safely converge to **one** stored transaction per bank. The same reference from different banks (e.g. Foodics and Acme) creates separate transactions, since references are bank-scoped. This approach is race-condition-safe under concurrent workers.

## Security

### Webhook token (authentication)

Each client row has a unique `webhook_token` column (48-char random string, auto-generated). The webhook URL uses this token instead of a numeric ID:

```
POST /api/webhooks/foodics/aB3xQ9...
```

This serves two purposes:
1. **Authentication** — a request with an unknown token is rejected with **401**.
2. **Obfuscation** — sequential numeric IDs are never exposed, preventing client enumeration.

### HMAC signature verification (integrity)

Banks that support payload signing send an `X-Webhook-Signature` header containing `HMAC-SHA256(shared_secret, raw_body)` as a hex digest. The [`VerifyWebhookSignature`](app/Http/Middleware/VerifyWebhookSignature.php) middleware checks this:

1. If no shared secret is configured for the bank (`config('wallet.bank_secrets.{bank}')` is `null`), the middleware is bypassed — useful during development.
2. If a secret is configured but the header is missing or the digest doesn't match, the request is rejected with **401**.

Configure per-bank secrets in `.env`:

```dotenv
WEBHOOK_SECRET_FOODICS=your-shared-secret-from-foodics
WEBHOOK_SECRET_ACME=your-shared-secret-from-acme
```

### Request body size limit

The [`LimitRequestBodySize`](app/Http/Middleware/LimitRequestBodySize.php) middleware rejects payloads exceeding **2 MB** (configurable via `WEBHOOK_MAX_BODY_BYTES` in `.env`) with **413 Payload Too Large**. It checks the `Content-Length` header first for an early reject, then verifies the actual body size as a safety net against missing or spoofed headers.

### Rate limiting

Webhook requests are throttled **per client token** (not per IP, since banks may rotate IPs). The default limit is **60 requests per minute** per token, configurable via `WEBHOOK_THROTTLE_PER_MINUTE` in `.env`. Requests exceeding the limit receive **429 Too Many Requests**.

## Pausing ingestion (without losing webhooks)

- **Config**: `WALLET_INGESTION_ENABLED` in `.env` (mirrors [`config/wallet.php`](config/wallet.php)).
- **Runtime override**: cache key `wallet:ingestion_enabled` (boolean). When present, it overrides config.

When ingestion is **off**, the HTTP handler still **stores** the payload in `webhook_receipts` with `ingestion_status = pending_dispatch` and returns **202**, but **does not** enqueue any jobs.

When ingestion is **on** again:

```bash
php artisan wallet:dispatch-pending-webhooks
```

That command dispatches pending receipts and their line jobs.

## Webhook receipt lifecycle

Each receipt transitions through these statuses, modelled by the [`IngestionStatus`](app/Wallet/Enums/IngestionStatus.php) backed enum:

| Status | Meaning |
|---|---|
| `pending_dispatch` | Payload stored, ingestion was off, no jobs created yet. |
| `dispatched` | `Bus::batch()` has been called; jobs are in the queue. `batch_id` is stored on the receipt for correlation with `job_batches`. |
| `completed` | The batch `finally` callback confirmed all jobs ran with zero failures. |
| `failed` | The `finally` callback detected at least one failed job. `allowFailures()` ensures every job still gets a chance to run; partial success is still marked `failed` to flag the receipt for investigation. |

## Architecture

- **Adapter contract**: [`WebhookBankAdapter`](app/Wallet/Contracts/WebhookBankAdapter.php) — parse one raw line → [`NormalizedIncomingTransaction`](app/Wallet/DTOs/NormalizedIncomingTransaction.php).
- **Concrete adapters**: [`FoodicsBankWebhookAdapter`](app/Wallet/Banking/FoodicsBankWebhookAdapter.php), [`AcmeBankWebhookAdapter`](app/Wallet/Banking/AcmeBankWebhookAdapter.php).
- **Resolver**: [`BankAdapterResolver`](app/Wallet/Banking/BankAdapterResolver.php) maps bank code → adapter (registered in [`AppServiceProvider`](app/Providers/AppServiceProvider.php)).
- **Batch dispatch**: [`WebhookLineDispatcher`](app/Wallet/WebhookLineDispatcher.php) collects one [`ImportTransactionLineJob`](app/Jobs/ImportTransactionLineJob.php) per non-empty line and dispatches them as a single `Bus::batch()`. Batch callbacks update the receipt status on completion or failure. Each job retries up to 3 times with exponential backoff (`2^attempt × 5` seconds: 10s, 20s, 40s).
- **Payment XML**: [`DomPaymentRequestXmlBuilder`](app/Wallet/Payment/DomPaymentRequestXmlBuilder.php) implements [`PaymentRequestXmlBuilder`](app/Wallet/Contracts/PaymentRequestXmlBuilder.php) by iterating over a list of self-contained [`XmlElement`](app/Wallet/Contracts/XmlElement.php) classes (one per XML section). Each element decides via `shouldInclude()` whether to render itself. Adding a new XML section means adding one class and registering it in `AppServiceProvider` — the builder itself never changes.
- **Security middleware**: [`LimitRequestBodySize`](app/Http/Middleware/LimitRequestBodySize.php) enforces a max payload size. [`VerifyWebhookSignature`](app/Http/Middleware/VerifyWebhookSignature.php) checks HMAC-SHA256 signatures per bank. Token-based client lookup in the controller prevents client enumeration.

## Production testing and observability

You can't trigger real bank webhooks or send real payments on demand, but you have three tools to verify the system works in a live environment.

### Simulate a webhook

Push a synthetic webhook through the full pipeline (receipt → batch → job → DB) without needing the bank to call you:

```bash
php artisan wallet:simulate-webhook foodics 1 --lines=5
```

This creates a receipt, dispatches a batch of 5 jobs with random amounts, and prints the final receipt status and batch ID. Works with both `foodics` and `acme` banks.

### Preview payment XML

Render a sample `PaymentRequestMessage` using the live builder and registered element classes:

```bash
php artisan wallet:preview-payment-xml
php artisan wallet:preview-payment-xml --notes="First note" --notes="Second" --payment-type=421 --charge-details=RB
```

### Health check

Single-glance pipeline status: receipt counts by status, stuck/failed receipt warnings, transaction volume:

```bash
php artisan wallet:health
```

Reports receipts stuck in `dispatched` for over 10 minutes, receipts in `failed` status, and transaction counts (total, last hour, last 24h).

### Structured logging

Every decision point in the pipeline emits a structured log entry with consistent context keys (`receipt_id`, `batch_id`, `bank`, `client_id`, `line_index`, `reference`). Key log messages:

| Log | Level | Meaning |
|---|---|---|
| Webhook received | info | Payload stored, includes `line_count` and `ingestion_enabled` |
| Webhook rejected: invalid token | warning | Unknown `webhook_token`, request was not processed |
| Webhook rejected: payload too large | warning | Body exceeds `max_body_bytes`, request was not processed |
| Webhook rejected: missing/invalid signature | warning | HMAC check failed, request was not processed |
| Webhook receipt batch dispatched | info | Batch created, includes `job_count` and `batch_id` |
| Transaction created | info | New transaction row inserted |
| Duplicate transaction skipped | info | Idempotent ignore via unique constraint |
| Unparseable transaction line | error | Adapter returned null, includes `raw_line` |
| Webhook receipt batch finished | info | Batch done, includes `total_jobs` and `failed_jobs` |

Filter by `receipt_id` in your log aggregator to trace a single webhook end-to-end.

## Tests

```bash
php artisan test
```

### Unit tests

- Foodics and Acme adapter parsing (valid lines, malformed input, European decimal format).
- Resolver case-insensitive lookup and unknown-bank exception.
- XML builder snapshot assertions (present and absent tags depending on defaults).

### Feature tests

- Webhook round-trip with sync queue: POST → receipt → batch → transactions in DB.
- Idempotency: same reference posted in two separate webhooks produces one row.
- Ingestion pause: receipt stored as `pending_dispatch`, no batch dispatched; replay via `wallet:dispatch-pending-webhooks` creates transactions.
- `Bus::assertBatched` verification of job count and type.
- Artisan commands: `wallet:simulate-webhook`, `wallet:preview-payment-xml`, `wallet:health`.

### Security tests

- Invalid/missing webhook token → **401**.
- HMAC signature required when bank secret is configured.
- Invalid HMAC signature → **401**.
- Valid HMAC signature → request processed.
- No HMAC required when no secret configured (dev mode).
- Each bank uses its own secret — cross-bank signatures fail.
- Oversized body → **413**.
- Rate limiting returns **429** when threshold exceeded.

### Stress / performance tests (`#[Group('slow')]`)

| Test | What it proves |
|---|---|
| 1000 Foodics lines parsed (unit) | Adapter parsing stays under 2s, no O(n^2) regex blowup. |
| 1000 Acme lines parsed (unit) | Same for the Acme format. |
| 1000 resolver lookups (unit) | Map lookup is O(1), not a hidden bottleneck. |
| 1000-line Foodics webhook end-to-end | HTTP → receipt → batch → 1000 DB inserts → receipt `completed`, under 10s. |
| 1000-line Acme webhook end-to-end | Same for Acme format. |
| 1000-line batch structure | Exactly 1 batch containing 1000 `ImportTransactionLineJob` instances. |
| Duplicate 1000-line webhook twice | Posts identical body twice, asserts count stays at 1000 (not 2000). |
| Same reference from two banks | 100 shared references via Foodics + Acme = 200 rows (bank-scoped uniqueness). |

## Payment XML rules

- No `<Notes>` element when there are no notes.
- No `<PaymentType>` when the value is `99`.
- No `<ChargeDetails>` when the value is `SHA` (case-insensitive).

Resolve the builder from the container: `app(PaymentRequestXmlBuilder::class)->build($paymentTransfer)`.
