<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `payment.pending_cash` — cash reference code issued; not yet paid. */
final class PaymentPendingCash extends AslanWebhookEvent {}
