<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

use Aslan\Payment\Dto\Subscription;
use Aslan\Payment\Dto\WebhookEvent;

/**
 * Base class for every Aslan webhook event. Carries the verified, decoded
 * delivery body: { id, event, createdAt, data }.
 */
abstract class AslanWebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public readonly array $payload) {}

    /** Event ID — use it to deduplicate retried deliveries. */
    public function id(): string
    {
        return (string) ($this->payload['id'] ?? '');
    }

    /** Event name, e.g. "subscription.renewed". */
    public function type(): string
    {
        return (string) ($this->payload['event'] ?? '');
    }

    /**
     * The event's `data` object.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $data = $this->payload['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * The (partial) subscription carried by a `subscription.*` event, hydrated
     * by the SDK. Only the fields present in the delivery are set (always `id`;
     * e.g. period dates on `subscription.renewed`, `failureCount` on
     * `subscription.payment_failed`). Null for any other event.
     */
    public function subscription(): ?Subscription
    {
        $event = WebhookEvent::fromArray($this->payload);
        $subscription = $event->data['subscription'] ?? null;

        if (! $event->isSubscriptionEvent() || ! is_array($subscription) || ! isset($subscription['id'])) {
            return null;
        }

        return $event->asSubscription();
    }
}
