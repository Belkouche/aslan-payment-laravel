# AGENTS.md: aslan/payment-laravel

Instructions for AI coding agents (Claude Code, Copilot, Cursor, Codex, ...) writing **Laravel application code** that uses this package, and for agents working **on this package** itself. Human-oriented documentation: [README.md](README.md).

## Facts

- Package `aslan/payment-laravel` 0.2.0, Laravel 12 or 13, wraps the SDK `aslan/payment-php` ^0.2.
- PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12` (2026-10-21 "php2shell" fixes, CVE-2026-109618 and CVE-2026-109619). Never relax it, never use `--ignore-platform-req=php`; upgrade PHP.
- API base URL `https://api.aslanpay.ma/pay`. Mode = key prefix: `sk_test_` (test) or `sk_live_` (live).
- Env: `ASLAN_API_KEY`, `ASLAN_WEBHOOK_SECRET` (plus optional `ASLAN_BASE_URL`, `ASLAN_TIMEOUT`, `ASLAN_MAX_RETRIES`, `ASLAN_WEBHOOK_ENABLED`, `ASLAN_WEBHOOK_TOLERANCE`, `ASLAN_WEBHOOK_PATH`, `ASLAN_WEBHOOK_MIDDLEWARE`).
- Amounts: integers in centimes, currency `MAD`. `14900` = 149.00 MAD.
- Auto-registered route: `POST /aslan/webhook`, name `aslan.webhook`, group `api`, verified by `Aslan\Laravel\Http\Middleware\VerifyAslanWebhookSignature`.
- Webhook signature: header `X-Webhook-Signature: t=<unix>,v1=<hex>`, `v1 = HMAC-SHA256(secret, "<t>.<rawBody>")`, tolerance 300 s, event name in the body's `event` field.

## Rules for application code

1. Call the API through `Aslan\Laravel\Facades\Aslan` (`Aslan::subscriptions()`, `Aslan::checkoutSessions()`, ...) or by injecting `Aslan\Payment\Aslan`. Do not `new Aslan(env(...))` in app code.
2. Integer centimes only. Never floats or strings for `amount`.
3. Subscription Paywall: `return Aslan::paywall($checkout);` (HTML auto-submit form POST). Never `redirect($checkout->redirectUrl)`.
4. Checkout sessions, `changeCard()` and `repay()` links are plain GET redirects: `redirect()->away($x->url)` / `redirect()->away($link->redirectUrl)`.
5. Webhooks are the source of truth. Change payment/subscription state only in listeners of `Aslan\Laravel\Events\*`, never in `successUrl` / `returnUrl` controllers.
6. Do not write signature verification code; the package does it on the raw body. Do not put the webhook route behind `web`, CSRF, session, or `auth`.
7. Listeners implement `ShouldQueue` and dedupe on `$event->id()` (e.g. `DB::table('aslan_webhook_events')->insertOrIgnore([...]) === 0` means already processed).
8. Create the customer with `Aslan::customers()->create(['email' => ..., 'name' => ..., 'phone' => '+212XXXXXXXXX'])` (email or phone required; 409 `CONFLICT` on a duplicate email) and store its id; customers cannot be searched by email (encrypted). Store `$checkout->subscriptionId` and `$checkout->transactionId` before returning the Paywall response. `subscriptions()->create()` has no idempotency key: guard double submission yourself.
9. `returnUrl`, `cancelUrl`, `callbackUrl` are required for `subscriptions()->create()`, `changeCard()`, `repay()` and must be `https://`.
10. Immediate `cancel($id)`, `cancel($id, atPeriodEnd: true)`, `uncancel()`, `pause()`, `resume()` send no webhook for that change: update local state from the returned `SubscriptionStatusChange`.
11. `$event->subscription()` is a partial `Aslan\Payment\Dto\Subscription` (always `id`; `status` usually null). Use `Aslan::subscriptions()->retrieve($id)` for authoritative state.
12. Dates from Aslan are ISO 8601 strings; wrap with `Carbon::parse()` before query-builder updates.
13. Catch `Aslan\Payment\AslanException`: `getErrorCode()` (string), `getCode()` (HTTP int), `requestId`. There is no `$e->code`.
14. Never log `ASLAN_API_KEY`, `ASLAN_WEBHOOK_SECRET`, `signingSecret`, `$checkout->paywall`, or raw webhook headers.
15. Default to `sk_test_` keys unless the user explicitly asks for live.
16. Trials: signup charges only a refunded verification amount (1.00 MAD by default); status `trialing` until `trialEndsAt`, where the first real charge is made and reported as `SubscriptionRenewed` (with the subscription `metadata`). A failed trial-end charge goes to `past_due` at once, then retries after 2, 5, 7 days. A `PaymentSucceeded` whose `data.transaction.metadata` has `__trialVerification` or `__changeCard` = true is a refunded card check, not a sale.
17. Billing facts: dunning retries after 2, 5 and 7 days (3rd failure = `past_due`, 4th = cancelled, reason `dunning`); month-based plans renew on a fixed UTC anchor day clamped to month end; a late catch-up charge restarts the period instead of billing missed ones; `priceOverride` applies to every period starting on or before `priceOverrideUntil`; `subscription.plan_changed` is sent on acceptance with `effectiveAt` = now or the current period end; plan changes in a trial never charge.
18. Checkout `create()` returns no transaction id and ignores unknown fields (`reference`, `description`): put `order_id` in `metadata` and match it in `PaymentSucceeded`. In the activation listener, `retrieve()` can still say `pending_initial` for a moment: release the job and retry.

## Intent to call

| Intent | Code |
|---|---|
| One-time payment | `$s = Aslan::checkoutSessions()->create(['amount' => 14900, 'currency' => 'MAD', 'successUrl' => ..., 'cancelUrl' => ..., 'metadata' => ['order_id' => ...]], idempotencyKey: ...);` store `$s->id`; `return redirect()->away($s->url);` |
| Order paid | Listener `handle(PaymentSucceeded $e)`, match `$e->data()['transaction']['metadata']['order_id']` |
| Create customer | `$c = Aslan::customers()->create(['email' => ..., 'name' => ...]);` store `$c->id` (409 = exists) |
| Refund | `Aslan::refunds()->create(['transactionId' => ..., 'amount' => ...], idempotencyKey: ...)` |
| Create plan (once) | `Aslan::plans()->create(['name' => ..., 'amount' => 9900, 'currency' => 'MAD', 'interval' => 'monthly'])` |
| Start subscription | customer id from `Aslan::customers()->create()`; `$c = Aslan::subscriptions()->create([...])`; save ids; `return Aslan::paywall($c);` |
| Activate | Listener on `PaymentSucceeded` matching the stored initial transaction id, then `retrieve()` |
| Renewal | Listener on `SubscriptionRenewed`: `$e->subscription()->currentPeriodEnd`, `$e->data()['transaction']['amount']` |
| Failed renewal | `SubscriptionPaymentFailed`, `SubscriptionPastDue`: notify, offer `changeCard()` |
| Ended | `SubscriptionCancelled` (`cancelReason`: period_end, dunning, abandoned): revoke access |
| New card | `$l = Aslan::subscriptions()->changeCard($id, [urls]); return redirect()->away($l->redirectUrl);` |
| First payment abandoned | `Aslan::subscriptions()->repay($id, [urls])` then GET redirect |
| Change plan | `Aslan::subscriptions()->changePlan($id, $planId, when: 'immediate'\|'period_end'\|'immediate_proration', allowDowngrade: false)` |
| Cancel | `cancel($id)` now; `cancel($id, atPeriodEnd: true)`; undo `uncancel($id)` |
| Pause / resume | `pause($id)` / `resume($id)` |
| Discount | `Aslan::subscriptions()->update($id, ['priceOverride' => 7900, 'priceOverrideMonths' => 3])` |
| PDFs | `Aslan::billingAttempts()->receiptPdf($id)`, `->facture($id)`, `Aslan::invoices()->pdf($id)` |
| Fake API in tests | `$this->app->instance(\Aslan\Payment\Aslan::class, new \Aslan\Payment\Aslan('sk_test_fake', httpClient: $fake));` |
| Signed webhook in tests | `Aslan::webhooks()->generateSignatureHeader($body)` as `HTTP_X_WEBHOOK_SIGNATURE`, `POST route('aslan.webhook')` with the same `$body` |

## Events

`payment.succeeded` PaymentSucceeded, `payment.failed` PaymentFailed, `payment.expired` PaymentExpired, `payment.pending_cash` PaymentPendingCash, `payment.cash_late_confirmation` PaymentCashLateConfirmation, `refund.created` RefundCreated, `refund.updated` RefundUpdated, `subscription.renewed` SubscriptionRenewed, `subscription.payment_failed` SubscriptionPaymentFailed, `subscription.past_due` SubscriptionPastDue, `subscription.cancelled` SubscriptionCancelled, `subscription.plan_changed` SubscriptionPlanChanged, `subscription.plan_change_failed` SubscriptionPlanChangeFailed. Plus `WebhookReceived` for every verified delivery. All in `Aslan\Laravel\Events\`, with `id()`, `type()`, `data()`, `subscription()`, `$payload`. `checkout_session.completed` and `transaction.captured` do not exist.

## Anti-patterns

- Location redirect to the Paywall (`redirect($checkout->redirectUrl)`).
- Marking paid / active on the browser redirect.
- Verifying re-encoded JSON (`$request->all()`, `json_encode(json_decode($raw))`).
- Floats or strings for money.
- Logging secrets or Paywall payloads.
- Webhook route in `routes/web.php`, behind CSRF, session, or `auth`.
- Synchronous, non-idempotent listeners.
- `cancel($id, ['atPeriodEnd' => true])` (the argument is a bool).
- `Aslan::client()->request('POST', '/api/v1/customers', ...)` (use `Aslan::customers()->create()`), or `customers()->list(['search' => $email])` to find a customer.
- Booking a verification `PaymentSucceeded` (`__trialVerification` / `__changeCard`) as revenue.
- Sending `reference` / `description` to `checkoutSessions()->create()` or storing `$session->transactionId` from `create()` (always null).

## Working on this package

- `composer install && composer test && composer audit`; `php -l` on changed files.
- Tests use Orchestra Testbench (`tests/`); every webhook rejection case must stay covered.
- The `path` repository in `composer.json` points at a local SDK checkout for development only.
- Use the Aslan brand only in code and docs.
