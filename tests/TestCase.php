<?php

namespace SloukaPetr\Moneta\Tests;

use SloukaPetr\Moneta\MonetaServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MonetaServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('moneta.sandbox', true);
        $app['config']->set('moneta.api_token', 'test-token');
        $app['config']->set('moneta.application_name', 'Test App');
        $app['config']->set('moneta.identification_number', '12345678');
        $app['config']->set('moneta.business_name', 'Test Business');
        $app['config']->set('moneta.base_url', 'https://api.moneta.cz');
    }
}
