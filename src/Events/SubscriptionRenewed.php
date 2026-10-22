<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `subscription.renewed` — renewal (or dunning retry, or trial-end charge) succeeded. */
final class SubscriptionRenewed extends AslanWebhookEvent {}
