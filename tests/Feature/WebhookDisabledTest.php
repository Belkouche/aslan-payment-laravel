<?php

declare(strict_types=1);

namespace Aslan\Laravel\Tests\Feature;

use Aslan\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class WebhookDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('aslan.webhook.enabled', false);
    }

    public function test_route_is_not_registered_when_disabled(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('aslan.webhook'));
    }
}
