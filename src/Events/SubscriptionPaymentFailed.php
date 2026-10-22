<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `subscription.payment_failed` — renewal charge failed; a retry is scheduled. */
final class SubscriptionPaymentFailed extends AslanWebhookEvent {}
