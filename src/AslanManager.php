<?php

declare(strict_types=1);

namespace Aslan\Laravel;

use Aslan\Payment\Aslan;
use Aslan\Payment\Resources\Webhooks;
use Illuminate\Contracts\Container\Container;

/**
 * Facade root. Proxies to the container-bound SDK client without depending on
 * resource internals:
 *
 *   Aslan::subscriptions()   -> $client->subscriptions   (any resource property)
 *   Aslan::isTestMode()      -> $client->isTestMode()    (any public method)
 *   Aslan::client()          -> the Aslan\Payment\Aslan instance
 */
final class AslanManager
{
    public function __construct(private readonly Container $container) {}

    public function client(): Aslan
    {
        return $this->container->make(Aslan::class);
    }

    /**
     * Webhook verifier bound to the configured webhook secret.
     */
    public function webhooks(?string $secret = null): Webhooks
    {
        return Aslan::webhooks($secret ?? (string) $this->container->make('config')->get('aslan.webhook.secret'));
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $client = $this->client();

        if (method_exists($client, $name)) {
            return $client->{$name}(...$arguments);
        }

        return $client->{$name};
    }
}
