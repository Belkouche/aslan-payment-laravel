<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `payment.succeeded` — payment confirmed (card or cash), including a subscription's initial payment. */
final class PaymentSucceeded extends AslanWebhookEvent {}
