<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `subscription.plan_change_failed` — scheduled period-end plan change could not be applied. */
final class SubscriptionPlanChangeFailed extends AslanWebhookEvent {}
