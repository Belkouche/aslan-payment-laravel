<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/**
 * `subscription.renewal_suspended` — a renewal was skipped because the subscription's PSP account no longer
 * allows recurring payments. Nothing was charged; read `reason`, `skippedPeriodStart`, `skippedPeriodEnd`
 * from `data()['subscription']`.
 */
final class SubscriptionRenewalSuspended extends AslanWebhookEvent {}
