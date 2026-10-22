<?php

declare(strict_types=1);

namespace Aslan\Laravel\Tests;

use Aslan\Laravel\AslanServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function getPackageProviders($app): array
    {
        return [AslanServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Aslan' => \Aslan\Laravel\Facades\Aslan::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('aslan.api_key', 'sk_test_abc123');
        $app['config']->set('aslan.webhook.secret', self::WEBHOOK_SECRET);
    }
}
