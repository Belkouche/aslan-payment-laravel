# Aslan Payment for Laravel

Laravel integration for the official Aslan PHP SDK, [`aslan/payment-php`](https://github.com/Belkouche/aslan-payment-php).
It gives you:

- a container-bound, lazily built `Aslan\Payment\Aslan` client configured from `.env`
- an `Aslan` facade that proxies to the client's resources (payments, plans, subscriptions, ...)
- a signed webhook endpoint that verifies `X-Webhook-Signature` and dispatches Laravel events

Requirements: PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`, Laravel 12 or 13 (Laravel 13 needs PHP 8.3+).

## Installation

```bash
composer require aslan/payment-laravel
```

The service provider and the `Aslan` facade alias are auto-discovered. Publish the config if you want to edit it:

```bash
php artisan vendor:publish --tag=aslan-config
```

> **Local development note:** this repository's `composer.json` contains a Composer `path`
> repository pointing at a local checkout of `aslan/payment-php`. It is removed once the SDK
> (`^0.2`) is published on Packagist.

## Configuration

| Env var | Config key | Default | Notes |
|---|---|---|---|
| `ASLAN_API_KEY` | `aslan.api_key` | — | `sk_live_...` or `sk_test_...`; the prefix selects live/test mode |
| `ASLAN_BASE_URL` | `aslan.base_url` | `https://api.aslanpay.ma/pay` | Same default as the SDK |
| `ASLAN_TIMEOUT` | `aslan.timeout` | `30` | Seconds |
| `ASLAN_MAX_RETRIES` | `aslan.max_retries` | `2` | Retries on transient errors |
| `ASLAN_WEBHOOK_ENABLED` | `aslan.webhook.enabled` | `true` | Register the webhook route |
| `ASLAN_WEBHOOK_SECRET` | `aslan.webhook.secret` | — | Your merchant webhook secret |
| `ASLAN_WEBHOOK_TOLERANCE` | `aslan.webhook.tolerance` | `300` | Max signature age in seconds (`0` disables) |
| `ASLAN_WEBHOOK_PATH` | `aslan.webhook.path` | `aslan/webhook` | `POST` route, named `aslan.webhook` |
| `ASLAN_WEBHOOK_MIDDLEWARE` | `aslan.webhook.middleware` | `api` | Middleware group for the route |

```dotenv
ASLAN_API_KEY=sk_test_<prefix>_<secret>
ASLAN_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxx
```

The client is built the first time it is resolved. If `ASLAN_API_KEY` is empty, resolving it throws
`Aslan\Laravel\Exceptions\MissingApiKeyException`; the application itself still boots (so
`artisan` commands keep working in environments without credentials).

There is no separate mode switch: an `sk_test_` key is test mode, an `sk_live_` key is live mode
(`Aslan::isTestMode()` / `Aslan::isLiveMode()`).

## Using the client

Every public resource property of the SDK client is exposed as a facade method of the same name,
and every public client method is forwarded as-is:

```php
use Aslan\Laravel\Facades\Aslan;

Aslan::checkoutSessions()->create([...]);   // $client->checkoutSessions->create([...])
Aslan::subscriptions()->retrieve($id);       // $client->subscriptions->retrieve($id)
Aslan::isTestMode();                         // $client->isTestMode()
Aslan::client();                             // the Aslan\Payment\Aslan instance itself
```

You can also inject the client directly:

```php
use Aslan\Payment\Aslan;

public function __construct(private Aslan $aslan) {}
```

The facade does not wrap or reinterpret SDK results — refer to the
[`aslan/payment-php` README](https://github.com/Belkouche/aslan-payment-php) for the exact
parameters and return types of each resource method.

## Subscriptions (AslanSubscription usage)

This package intentionally ships no models or migrations. Store the IDs you need (plan ID,
subscription ID, your own invoice ID in `metadata`) in your own tables and react to webhooks.

Amounts are integers in **centimes** (`9900` = 99.00 MAD).

### 1. Create a plan (once)

```php
$plan = Aslan::plans()->create([
    'name'     => 'Pro monthly',
    'amount'   => 9900,
    'currency' => 'MAD',
    'interval' => 'monthly',    // daily | weekly | monthly | quarterly | yearly
    'trialDays'=> 0,
]);
```

### 2. Subscribe a customer and redirect to the payment page

```php
$customer = Aslan::customers()->create([...]);

$result = Aslan::subscriptions()->create([
    'planId'      => $planId,
    'customerId'  => $customer->id,
    'returnUrl'   => route('billing.return'),
    'cancelUrl'   => route('billing.cancel'),
    'callbackUrl' => route('aslan.webhook'), // required (HTTPS) but ignored; events go to your merchant webhook URL
    'metadata'    => ['invoice_id' => (string) $invoice->id],
]);

// Persist $result's subscriptionId / transactionId, then send the customer
// to the hosted payment page that saves their card:
return redirect()->away($result->redirectUrl);
```

The subscription stays `pending_initial` until the first payment succeeds. You are notified
by a `payment.succeeded` webhook (`PaymentSucceeded`) whose transaction carries your
subscription `metadata`. On a trial plan, only a small card-verification amount is charged and
refunded automatically; its `payment.succeeded` carries no subscription metadata — the first real
charge arrives as `subscription.renewed` at trial end.

### 3. Handle renewals and failures

```php
// app/Listeners/HandleAslanRenewal.php
use Aslan\Laravel\Events\SubscriptionRenewed;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleAslanRenewal implements ShouldQueue
{
    public function handle(SubscriptionRenewed $event): void
    {
        $subscription = $event->data()['subscription'];

        // Deduplicate on $event->id(): deliveries are retried.
        Invoice::markPaid($subscription['metadata']['invoice_id'] ?? null, $event->id());
    }
}
```

```php
use Aslan\Laravel\Events\SubscriptionPaymentFailed;
use Aslan\Laravel\Events\SubscriptionPastDue;
use Illuminate\Support\Facades\Event;

Event::listen(function (SubscriptionPaymentFailed $event) {
    // A retry is scheduled (dunning ladder: 2, 5, 7 days). Tell the customer.
});

Event::listen(function (SubscriptionPastDue $event) {
    // Restrict access until the subscription is paid.
});
```

Laravel's event discovery picks up listeners type-hinting these events automatically.

### 4. Change card, cancel, resume

```php
// Collect a new card: returns a payment-link URL to send the customer to.
$link = Aslan::subscriptions()->changeCard($subscriptionId, [
    'returnUrl'   => route('billing.card-updated'),
    'cancelUrl'   => route('billing.show'),
    'callbackUrl' => route('aslan.webhook'),
]);

// Cancel immediately, or at the end of the current period.
Aslan::subscriptions()->cancel($subscriptionId);
Aslan::subscriptions()->cancel($subscriptionId, ['atPeriodEnd' => true]);

// Reverse a scheduled period-end cancellation.
Aslan::subscriptions()->uncancel($subscriptionId);

// Pause / resume.
Aslan::subscriptions()->pause($subscriptionId);
Aslan::subscriptions()->resume($subscriptionId);
```

Check the SDK README for the exact signatures of these methods.

## Webhooks

The route `POST /aslan/webhook` (name `aslan.webhook`) is registered automatically. Configure
its full URL as your webhook URL in the Aslan dashboard and set `ASLAN_WEBHOOK_SECRET`.

Every delivery goes through `VerifyAslanWebhookSignature`:

- missing or malformed `X-Webhook-Signature`, wrong signature, or a timestamp more than
  `ASLAN_WEBHOOK_TOLERANCE` seconds away from now → `401`, nothing dispatched
- valid signature but the body is not JSON or has no `event` field → `400`, nothing dispatched
- valid → `200 {"received": true}`

It then dispatches `Aslan\Laravel\Events\WebhookReceived` for every delivery, plus the typed event
below when the name is known. Unknown (future) event names only dispatch `WebhookReceived`.

| `event` | Laravel event (`Aslan\Laravel\Events\...`) |
|---|---|
| `payment.succeeded` | `PaymentSucceeded` |
| `payment.failed` | `PaymentFailed` |
| `payment.expired` | `PaymentExpired` |
| `payment.pending_cash` | `PaymentPendingCash` |
| `payment.cash_late_confirmation` | `PaymentCashLateConfirmation` |
| `refund.created` | `RefundCreated` |
| `refund.updated` | `RefundUpdated` |
| `subscription.renewed` | `SubscriptionRenewed` |
| `subscription.payment_failed` | `SubscriptionPaymentFailed` |
| `subscription.past_due` | `SubscriptionPastDue` |
| `subscription.cancelled` | `SubscriptionCancelled` |
| `subscription.plan_changed` | `SubscriptionPlanChanged` |
| `subscription.plan_change_failed` | `SubscriptionPlanChangeFailed` |

All events extend `AslanWebhookEvent` and expose:

- `$event->payload` — the full decoded body (`id`, `event`, `createdAt`, `data`)
- `$event->id()` — event ID, use it to deduplicate retried deliveries
- `$event->type()` — the event name
- `$event->data()` — the `data` object (`{ transaction }` for `payment.*`, the refund for
  `refund.*`, `{ subscription, transaction? }` for `subscription.*`)

Listeners run synchronously inside the request. Make them `ShouldQueue` for anything slow so
the endpoint answers quickly.

### CSRF

The route is registered with the `api` middleware group, outside `web`, so CSRF verification
does not apply. If you change `ASLAN_WEBHOOK_MIDDLEWARE` to `web`, exclude the path in
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['aslan/webhook']);
})
```

### Custom route

Set `ASLAN_WEBHOOK_ENABLED=false` and register your own:

```php
use Aslan\Laravel\Http\Controllers\WebhookController;
use Aslan\Laravel\Http\Middleware\VerifyAslanWebhookSignature;

Route::post('/hooks/aslan', WebhookController::class)
    ->middleware(VerifyAslanWebhookSignature::class);
```

## Testing your application

**Fake the client.** Swap the container binding with an SDK client that uses a fake HTTP client
(the SDK's `HttpClientInterface` is its test seam). The facade resolves the client on every call,
so the swap applies immediately:

```php
use Aslan\Payment\Aslan;
use Aslan\Payment\HttpClient\HttpClientInterface;

$http = new class implements HttpClientInterface {
    public array $calls = [];
    public function request(string $method, string $url, array $body = [], array $headers = [], array $query = []): array
    {
        $this->calls[] = compact('method', 'url', 'body');
        return ['data' => ['subscriptionId' => 'sub_1', 'redirectUrl' => 'https://pay.test/x']];
    }
};

$this->app->instance(Aslan::class, new Aslan('sk_test_fake', httpClient: $http));
```

**Simulate webhooks.** Sign the exact body you send:

```php
use Illuminate\Support\Facades\Event;
use Aslan\Laravel\Events\SubscriptionRenewed;

Event::fake([SubscriptionRenewed::class]);

$body = json_encode(['id' => 'evt_1', 'event' => 'subscription.renewed', 'data' => [...]]);
$t = time();
$signature = "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$body}", config('aslan.webhook.secret'));

$this->call('POST', '/aslan/webhook', server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
], content: $body)->assertOk();

Event::assertDispatched(SubscriptionRenewed::class);
```

## Security

- **PHP floor.** The package requires PHP `~8.2.35 || ~8.3.36 || ~8.4.27 || >=8.5.12`. These are
  the releases of 2026-10-21 that fix the critical unauthenticated remote code execution
  vulnerabilities CVE-2026-109618 and CVE-2026-109619 ("php2shell"). Composer refuses to
  install on older patch releases on purpose — upgrade PHP rather than overriding the platform.
- **Webhook verification.** Deliveries are verified with HMAC-SHA256 over `<t>.<raw body>` keyed
  by your webhook secret, compared in constant time (`hash_equals`). The timestamp must be within
  `ASLAN_WEBHOOK_TOLERANCE` seconds of the server clock (both directions) to limit replay; keep
  server time in sync (NTP). Events are only dispatched after verification succeeds. Still
  deduplicate on the event ID, since legitimate deliveries are retried.
- **Secrets.** Keep `ASLAN_API_KEY` and `ASLAN_WEBHOOK_SECRET` in the environment, never in
  version control. A missing webhook secret makes the endpoint fail with a `500` (and an exception
  in your logs) rather than accept unverified deliveries.

## Development

```bash
composer install
composer test
composer audit
```

## License

MIT. See [LICENSE](LICENSE).
