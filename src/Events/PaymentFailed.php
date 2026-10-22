<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `payment.failed` — payment declined or errored. */
final class PaymentFailed extends AslanWebhookEvent {}
