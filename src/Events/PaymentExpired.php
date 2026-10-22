<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `payment.expired` — session or cash code expired without payment. */
final class PaymentExpired extends AslanWebhookEvent {}
