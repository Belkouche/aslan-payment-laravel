<?php

declare(strict_types=1);

namespace Aslan\Laravel\Facades;

use Aslan\Laravel\AslanManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aslan\Payment\Aslan client()
 * @method static \Aslan\Payment\Resources\Webhooks webhooks(?string $secret = null)
 * @method static \Illuminate\Http\Response paywall(\Aslan\Payment\Dto\SubscriptionCheckout $checkout)
 * @method static \Aslan\Payment\Resources\CheckoutSessions checkoutSessions()
 * @method static \Aslan\Payment\Resources\Transactions transactions()
 * @method static \Aslan\Payment\Resources\PaymentLinks paymentLinks()
 * @method static \Aslan\Payment\Resources\Refunds refunds()
 * @method static \Aslan\Payment\Resources\Vendors vendors()
 * @method static \Aslan\Payment\Resources\Customers customers()
 * @method static \Aslan\Payment\Resources\ApiKeys apiKeys()
 * @method static \Aslan\Payment\Resources\WebhooksConfig webhooksConfig()
 * @method static \Aslan\Payment\Resources\Plans plans()
 * @method static \Aslan\Payment\Resources\Subscriptions subscriptions()
 * @method static \Aslan\Payment\Resources\BillingAttempts billingAttempts()
 * @method static \Aslan\Payment\Resources\Invoices invoices()
 * @method static bool isTestMode()
 * @method static bool isLiveMode()
 * @method static string getBaseUrl()
 *
 * @see \Aslan\Laravel\AslanManager
 */
class Aslan extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AslanManager::class;
    }
}
