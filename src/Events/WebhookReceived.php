<?php

declare(strict_types=1);

namespace Aslan\Laravel\Events;

/** Dispatched for every verified delivery, including unknown event names. */
final class WebhookReceived extends AslanWebhookEvent {}
