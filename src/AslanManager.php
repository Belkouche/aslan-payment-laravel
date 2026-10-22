<?php

declare(strict_types=1);

namespace Aslan\Laravel;

use Aslan\Payment\Aslan;
use Aslan\Payment\Dto\SubscriptionCheckout;
use Aslan\Payment\Resources\Webhooks;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Response;

/**
 * Facade root. Proxies to the container-bound SDK client without depending on
 * resource internals:
 *
 *   Aslan::subscriptions()   -> $client->subscriptions   (any resource property)
 *   Aslan::isTestMode()      -> $client->isTestMode()    (any public method)
 *   Aslan::client()          -> the Aslan\Payment\Aslan instance
 *   Aslan::paywall($checkout) -> HTML response that sends the browser to the Paywall
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
     * Send the customer to the hosted Paywall returned by subscriptions()->create().
     *
     * The Paywall only accepts an HTML form POST of `payload` + `signature` to
     * `redirectUrl`; a Location redirect to that URL does not work. This returns
     * a 200 HTML page that auto-submits that form (values HTML-escaped by the
     * SDK). It is never cached: the payload is single-use.
     */
    public function paywall(SubscriptionCheckout $checkout): Response
    {
        return new Response($checkout->autoSubmitFormHtml(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
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
