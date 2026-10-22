<?php

declare(strict_types=1);

namespace Aslan\Laravel\Tests\Feature;

use Aslan\Laravel\Events\AslanWebhookEvent;
use Aslan\Laravel\Events\PaymentSucceeded;
use Aslan\Laravel\Events\SubscriptionPaymentFailed;
use Aslan\Laravel\Events\SubscriptionRenewed;
use Aslan\Laravel\Events\WebhookReceived;
use Aslan\Laravel\Http\Controllers\WebhookController;
use Aslan\Laravel\Facades\Aslan;
use Aslan\Laravel\Tests\TestCase;
use Aslan\Payment\Dto\Subscription;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

final class WebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([WebhookReceived::class, ...array_values(WebhookController::EVENTS)]);
    }

    private function payload(string $event = 'subscription.renewed'): string
    {
        return json_encode([
            'id' => 'evt_123',
            'event' => $event,
            'createdAt' => '2026-10-22T10:00:00.000Z',
            'data' => ['subscription' => ['id' => 'sub_1', 'status' => 'active', 'metadata' => ['invoice_id' => 'inv_9']]],
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $body, string $secret = self::WEBHOOK_SECRET, ?int $t = null): string
    {
        $t ??= time();

        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
    }

    private function send(string $body, ?string $signature): TestResponse
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $headers['HTTP_X_WEBHOOK_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/aslan/webhook', [], [], [], $headers, $body);
    }

    public function test_valid_signature_dispatches_generic_and_typed_events(): void
    {
        $body = $this->payload('subscription.renewed');

        $this->send($body, $this->sign($body))->assertOk()->assertJson(['received' => true]);

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $e) => $e->id() === 'evt_123'
            && $e->type() === 'subscription.renewed');
        Event::assertDispatched(SubscriptionRenewed::class, fn (SubscriptionRenewed $e) => $e->data()['subscription']['metadata']['invoice_id'] === 'inv_9'
            && $e->payload === json_decode($body, true));
        Event::assertDispatchedTimes(SubscriptionRenewed::class, 1);
        Event::assertNotDispatched(SubscriptionPaymentFailed::class);
    }

    public function test_every_known_event_name_maps_to_its_typed_event(): void
    {
        foreach (WebhookController::EVENTS as $name => $class) {
            $body = $this->payload($name);
            $this->send($body, $this->sign($body))->assertOk();
            Event::assertDispatched($class, fn (AslanWebhookEvent $e) => $e->type() === $name);
        }

        Event::assertDispatchedTimes(WebhookReceived::class, count(WebhookController::EVENTS));
        $this->assertCount(13, WebhookController::EVENTS);
    }

    public function test_unknown_event_dispatches_only_the_generic_event(): void
    {
        $body = $this->payload('something.new');

        $this->send($body, $this->sign($body))->assertOk();

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatchedTimes(WebhookReceived::class, 1);
        foreach (WebhookController::EVENTS as $class) {
            Event::assertNotDispatched($class);
        }
    }

    public static function rejectedDeliveries(): array
    {
        return [
            'missing header' => [fn (string $b) => null, 401],
            'empty header' => [fn (string $b) => '', 401],
            'malformed header' => [fn (string $b) => 'garbage', 401],
            'wrong secret' => [fn (string $b) => self::signStatic($b, 'whsec_wrong', time()), 401],
            'expired timestamp' => [fn (string $b) => self::signStatic($b, self::WEBHOOK_SECRET, time() - 301), 401],
            'future timestamp' => [fn (string $b) => self::signStatic($b, self::WEBHOOK_SECRET, time() + 301), 401],
            'signature for another body' => [fn (string $b) => self::signStatic($b . ' ', self::WEBHOOK_SECRET, time()), 401],
            'missing timestamp' => [fn (string $b) => 'v1=' . hash_hmac('sha256', time() . '.' . $b, self::WEBHOOK_SECRET), 401],
            'missing v1' => [fn (string $b) => 't=' . time(), 401],
            'empty v1' => [fn (string $b) => 't=' . time() . ',v1=', 401],
            'non-digit timestamp suffix' => [fn (string $b) => self::signWithRawT($b, time() . 'abc', time()), 401],
            'signed timestamp' => [fn (string $b) => self::signWithRawT($b, '+' . time(), time()), 401],
            'v0 scheme only' => [fn (string $b) => 't=' . time() . ',v0=' . hash_hmac('sha256', time() . '.' . $b, self::WEBHOOK_SECRET), 401],
        ];
    }

    private static function signStatic(string $body, string $secret, int $t): string
    {
        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
    }

    /** Header whose `t` field is $rawT while the HMAC is computed over the numeric $t. */
    private static function signWithRawT(string $body, string $rawT, int $t): string
    {
        return 't=' . $rawT . ',v1=' . hash_hmac('sha256', $t . '.' . $body, self::WEBHOOK_SECRET);
    }

    #[DataProvider('rejectedDeliveries')]
    public function test_bad_signatures_are_rejected_without_dispatch(\Closure $signature, int $status): void
    {
        $body = $this->payload('payment.succeeded');

        $this->send($body, $signature($body))->assertStatus($status);

        Event::assertNothingDispatched();
    }

    public function test_tampered_body_is_rejected_without_dispatch(): void
    {
        $body = $this->payload('payment.succeeded');
        $signature = $this->sign($body);
        $tampered = str_replace('sub_1', 'sub_2', $body);

        $this->send($tampered, $signature)->assertStatus(401);

        Event::assertNothingDispatched();
    }

    public function test_signed_invalid_json_is_rejected_with_400(): void
    {
        $body = '{not json';

        $this->send($body, $this->sign($body))->assertStatus(400);

        Event::assertNothingDispatched();
    }

    public function test_signed_body_without_event_name_is_rejected_with_400(): void
    {
        $body = json_encode(['id' => 'evt_1', 'data' => []]);

        $this->send($body, $this->sign($body))->assertStatus(400);

        Event::assertNothingDispatched();
    }

    public function test_tolerance_is_configurable(): void
    {
        $this->app['config']->set('aslan.webhook.tolerance', 600);
        $body = $this->payload('payment.succeeded');

        $this->send($body, $this->sign($body, t: time() - 500))->assertOk();

        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_any_matching_v1_signature_is_accepted(): void
    {
        $body = $this->payload('payment.succeeded');
        $t = time();
        $header = 't=' . $t . ',v1=' . str_repeat('0', 64) . ',v1=' . hash_hmac('sha256', $t . '.' . $body, self::WEBHOOK_SECRET);

        $this->send($body, $header)->assertOk();

        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_zero_tolerance_disables_the_age_check(): void
    {
        $this->app['config']->set('aslan.webhook.tolerance', 0);
        $body = $this->payload('payment.succeeded');

        $this->send($body, $this->sign($body, t: time() - 86400))->assertOk();

        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_sdk_signature_helper_produces_accepted_headers(): void
    {
        $body = $this->payload('subscription.renewed');

        $this->send($body, Aslan::webhooks()->generateSignatureHeader($body))->assertOk();

        Event::assertDispatched(SubscriptionRenewed::class);
    }

    public function test_subscription_accessor_hydrates_the_sdk_dto(): void
    {
        $body = json_encode([
            'id' => 'evt_9',
            'event' => 'subscription.renewed',
            'createdAt' => '2026-10-22T10:00:00.000Z',
            'data' => [
                'subscription' => [
                    'id' => 'sub_42',
                    'planId' => 'plan_1',
                    'currentPeriodStart' => '2026-10-22T00:00:00.000Z',
                    'currentPeriodEnd' => '2026-11-22T00:00:00.000Z',
                    'nextBillingAt' => '2026-11-22T00:00:00.000Z',
                ],
                'transaction' => ['id' => 'txn_7', 'amount' => 14900, 'currency' => 'MAD'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->send($body, $this->sign($body))->assertOk();

        Event::assertDispatched(SubscriptionRenewed::class, function (SubscriptionRenewed $e): bool {
            $sub = $e->subscription();

            return $sub instanceof Subscription
                && $sub->id === 'sub_42'
                && $sub->planId === 'plan_1'
                && $sub->currentPeriodEnd === '2026-11-22T00:00:00.000Z'
                && $e->data()['transaction']['amount'] === 14900;
        });
    }

    public function test_subscription_accessor_is_null_for_other_events(): void
    {
        $this->assertNull((new PaymentSucceeded(json_decode($this->payload('payment.succeeded'), true)))->subscription());
        $this->assertNull((new SubscriptionRenewed(['id' => 'evt_1', 'event' => 'subscription.renewed', 'data' => []]))->subscription());
        $this->assertSame('sub_1', (new WebhookReceived(json_decode($this->payload('subscription.cancelled'), true)))->subscription()?->id);
    }

    public function test_missing_webhook_secret_is_a_server_error_without_dispatch(): void
    {
        $this->app['config']->set('aslan.webhook.secret', null);
        $body = $this->payload();

        $this->send($body, $this->sign($body))->assertStatus(500);

        Event::assertNothingDispatched();
    }
}
