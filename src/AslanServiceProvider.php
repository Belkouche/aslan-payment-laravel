<?php

declare(strict_types=1);

namespace Aslan\Laravel;

use Aslan\Laravel\Exceptions\MissingApiKeyException;
use Aslan\Laravel\Http\Controllers\WebhookController;
use Aslan\Laravel\Http\Middleware\VerifyAslanWebhookSignature;
use Aslan\Payment\Aslan;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AslanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/aslan.php', 'aslan');

        $this->app->singleton(Aslan::class, static function ($app): Aslan {
            $config = $app['config']->get('aslan');

            if (empty($config['api_key'])) {
                throw MissingApiKeyException::make();
            }

            return new Aslan(
                secretKey: $config['api_key'],
                baseUrl: $config['base_url'],
                timeout: (int) $config['timeout'],
                maxRetries: (int) $config['max_retries'],
            );
        });

        $this->app->singleton(AslanManager::class, static fn ($app) => new AslanManager($app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/aslan.php' => $this->app->configPath('aslan.php'),
            ], 'aslan-config');
        }

        $this->registerWebhookRoute();
    }

    private function registerWebhookRoute(): void
    {
        $config = $this->app['config']->get('aslan.webhook');

        if (! $config['enabled']) {
            return;
        }

        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        Route::middleware(array_filter((array) $config['middleware']))
            ->post($config['path'], WebhookController::class)
            ->middleware(VerifyAslanWebhookSignature::class)
            ->name('aslan.webhook');
    }
}
