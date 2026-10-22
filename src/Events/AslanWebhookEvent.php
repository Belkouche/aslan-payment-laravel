<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

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
}
