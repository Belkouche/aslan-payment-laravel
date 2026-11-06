# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `subscription.renewal_suspended` webhook event, dispatched as
  `Aslan\Laravel\Events\SubscriptionRenewalSuspended`.

## [0.2.0] - 2026-10-22

### Added

- `Aslan::paywall(SubscriptionCheckout $checkout)`: returns a 200 HTML response
  (`Cache-Control: no-store`) that auto-submits the Paywall form POST. The Paywall
  rejects a plain redirect to `redirectUrl`.
- `AslanWebhookEvent::subscription()`: the SDK `Subscription` DTO (partial) for
  `subscription.*` events, `null` otherwise.
- `AGENTS.md` and `llms.txt` for AI coding agents.
- Tests for the remaining signature rejection cases (missing `t`, missing/empty `v1`,
  non-digit `t`, unknown scheme), multiple `v1` values, `tolerance = 0`, and
  compatibility with the SDK's `generateSignatureHeader()`.

### Changed

- Built against the final `aslan/payment-php` 0.2.0 (fixed `AslanException`,
  `X-Webhook-Signature`, plans/subscriptions/billing attempts/invoices).
- The webhook middleware stays the package's own verifier (documented in the class):
  the SDK's verifier accepts a non-digit `t` and throws `JsonException` on a signed
  non-JSON body.
- README rewritten: every example now matches the real SDK methods and return types.

### Fixed

- Built against SDK 0.2.0 with DTOs mapped to the API's real response shapes and
  `Customers::create()`; the README now creates customers with
  `Aslan::customers()->create(...)` instead of the `@internal` raw request.
- Documentation re-verified against the current API: the one-time payment example no
  longer stores `$session->transactionId` from `create()` (always null) nor sends
  `reference` / `description` (dropped by the API); it matches the order through
  `metadata`. Trials (refunded verification charge, first charge at trial end,
  trial-end failure goes to `past_due`), billing anchor day and catch-up re-anchoring,
  price-override window, plan changes in a trial, `subscription.plan_changed`
  `effectiveAt` (current period end for `period_end`, not null), `subscription.renewed`
  metadata, `payment.succeeded` for verification charges, error codes
  (`VALIDATION_ERROR` is 400, `PSP_ERROR`, meaning of `409`), and the activation race
  (`retrieve()` may still say `pending_initial`) are corrected.
- Documentation: the 0.1.0 README sent customers to the Paywall with
  `redirect()->away($result->redirectUrl)` (does not work) and called
  `cancel($id, ['atPeriodEnd' => true])` (the argument is a bool).

## [0.1.0] - 2026-10-22

### Added

- `AslanServiceProvider` (auto-discovered): merges and publishes `config/aslan.php`,
  binds `Aslan\Payment\Aslan` as a lazily built singleton, registers the webhook route.
- `Aslan` facade proxying to the SDK client (`Aslan::client()`, `Aslan::subscriptions()`,
  `Aslan::plans()`, ... and the client's public methods).
- `MissingApiKeyException` thrown when the client is resolved without `ASLAN_API_KEY`.
- `VerifyAslanWebhookSignature` middleware for the `X-Webhook-Signature`
  (`t=<unix>,v1=<hmac_sha256_hex>`) scheme, with constant-time comparison and a
  configurable timestamp tolerance.
- `WebhookController` dispatching `WebhookReceived` plus one typed event per API event name.
- Requires PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12` (php2shell fixes) and Laravel 12 or 13.
