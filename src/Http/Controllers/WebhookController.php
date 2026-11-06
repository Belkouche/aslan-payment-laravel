<?php

declare(strict_types=1);

namespace Aslan\Laravel\Http\Controllers;

use Aslan\Laravel\Events;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives signature-verified deliveries (see VerifyAslanWebhookSignature)
 * and dispatches WebhookReceived plus the typed event, if the name is known.
 * Do slow work in queued listeners so this returns 200 quickly.
 */
final class WebhookController
{
    /** @var array<string, class-string<Events\AslanWebhookEvent>> */
    public const EVENTS = [
        'payment.succeeded' => Events\PaymentSucceeded::class,
        'payment.failed' => Events\PaymentFailed::class,
        'payment.expired' => Events\PaymentExpired::class,
        'payment.pending_cash' => Events\PaymentPendingCash::class,
        'payment.cash_late_confirmation' => Events\PaymentCashLateConfirmation::class,
        'refund.created' => Events\RefundCreated::class,
        'refund.updated' => Events\RefundUpdated::class,
        'subscription.renewed' => Events\SubscriptionRenewed::class,
        'subscription.payment_failed' => Events\SubscriptionPaymentFailed::class,
        'subscription.past_due' => Events\SubscriptionPastDue::class,
        'subscription.cancelled' => Events\SubscriptionCancelled::class,
        'subscription.plan_changed' => Events\SubscriptionPlanChanged::class,
        'subscription.plan_change_failed' => Events\SubscriptionPlanChangeFailed::class,
        'subscription.renewal_suspended' => Events\SubscriptionRenewalSuspended::class,
    ];

    public function __invoke(Request $request, Dispatcher $events): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! is_string($payload['event'] ?? null) || $payload['event'] === '') {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }

        $events->dispatch(new Events\WebhookReceived($payload));

        if (isset(self::EVENTS[$payload['event']])) {
            $class = self::EVENTS[$payload['event']];
            $events->dispatch(new $class($payload));
        }

        return new JsonResponse(['received' => true]);
    }
}
