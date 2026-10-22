<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** `payment.cash_late_confirmation` — cash received after the grace window; needs manual resolution. */
final class PaymentCashLateConfirmation extends AslanWebhookEvent {}
