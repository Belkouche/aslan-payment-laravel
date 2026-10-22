<?php

declare(strict_types=1);

namespace Aslan\Laravel\Tests\Feature;

use Aslan\Laravel\AslanManager;
use Aslan\Laravel\Exceptions\MissingApiKeyException;
use Aslan\Laravel\Facades\Aslan as AslanFacade;
use Aslan\Laravel\Tests\TestCase;
use Aslan\Payment\Aslan;
use Aslan\Payment\HttpClient\HttpClientInterface;
use Aslan\Payment\Resources\Customers;
use Aslan\Payment\Resources\Webhooks;
use Illuminate\Support\Facades\Route;

final class ServiceProviderTest extends TestCase
{
    public function test_config_is_merged_with_defaults(): void
    {
        $config = $this->app['config']->get('aslan');

        $this->assertSame('https://api.aslanpay.ma/pay', $config['base_url']);
        $this->assertSame(30, $config['timeout']);
        $this->assertSame(2, $config['max_retries']);
        $this->assertSame(300, $config['webhook']['tolerance']);
        $this->assertSame('aslan/webhook', $config['webhook']['path']);
        $this->assertTrue($config['webhook']['enabled']);
        $this->assertSame('api', $config['webhook']['middleware']);
    }

    public function test_client_is_a_singleton_built_from_config(): void
    {
        $this->app['config']->set('aslan.base_url', 'https://example.test/pay');

        $client = $this->app->make(Aslan::class);

        $this->assertInstanceOf(Aslan::class, $client);
        $this->assertSame($client, $this->app->make(Aslan::class));
        $this->assertSame('https://example.test/pay', $client->getBaseUrl());
        $this->assertTrue($client->isTestMode());
    }

    public function test_missing_api_key_fails_at_resolution_not_boot(): void
    {
        $this->app['config']->set('aslan.api_key', null);

        // Booting the app and resolving the manager must not throw.
        $this->assertInstanceOf(AslanManager::class, $this->app->make(AslanManager::class));

        $this->expectException(MissingApiKeyException::class);
        $this->expectExceptionMessage('ASLAN_API_KEY');

        $this->app->make(Aslan::class);
    }

    public function test_facade_proxies_resource_properties_and_methods(): void
    {
        $client = $this->app->make(Aslan::class);

        $this->assertSame($client, AslanFacade::client());
        $this->assertSame($client->customers, AslanFacade::customers());
        $this->assertInstanceOf(Customers::class, AslanFacade::customers());
        $this->assertTrue(AslanFacade::isTestMode());
        $this->assertFalse(AslanFacade::isLiveMode());
        $this->assertInstanceOf(Webhooks::class, AslanFacade::webhooks());
    }

    public function test_facade_uses_a_swapped_client_instance(): void
    {
        $http = new class implements HttpClientInterface {
            public array $calls = [];

            public function request(string $method, string $url, array $body = [], array $headers = [], array $query = []): array
            {
                $this->calls[] = [$method, $url];

                return ['data' => [], 'pagination' => ['page' => 1, 'pageSize' => 20, 'total' => 0, 'totalPages' => 0]];
            }
        };

        $this->app->instance(Aslan::class, new Aslan('sk_test_fake', 'https://fake.test', httpClient: $http));

        AslanFacade::customers()->list();

        $this->assertSame([['GET', 'https://fake.test/api/v1/customers']], $http->calls);
    }

    public function test_webhook_route_is_registered(): void
    {
        $route = Route::getRoutes()->getByName('aslan.webhook');

        $this->assertNotNull($route);
        $this->assertSame('aslan/webhook', $route->uri());
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('api', $route->middleware());
        $this->assertNotContains('web', $route->middleware());
    }
}
