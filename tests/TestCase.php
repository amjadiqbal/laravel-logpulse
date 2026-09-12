<?php

// tests/TestCase.php

namespace AmjadIqbal\LogPulse\Tests;

use AmjadIqbal\LogPulse\LogPulseServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LogPulseServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup LogPulse config
        $app['config']->set('logpulse.storage.table', 'logpulse_events');
        $app['config']->set('logpulse.storage.driver', 'database');
        $app['config']->set('logpulse.dashboard.middleware', ['web']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}