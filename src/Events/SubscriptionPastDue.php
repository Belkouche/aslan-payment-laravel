<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `subscription.past_due` — subscription moved to past_due. */
final class SubscriptionPastDue extends AslanWebhookEvent {}
