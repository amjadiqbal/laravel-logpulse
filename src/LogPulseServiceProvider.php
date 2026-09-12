<?php

// src/LogPulseServiceProvider.php

namespace AmjadIqbal\LogPulse;

use AmjadIqbal\LogPulse\Commands\LogPulseInstallCommand;
use AmjadIqbal\LogPulse\Commands\LogPulseMonitorCommand;
use AmjadIqbal\LogPulse\Commands\LogPulseQuickstartCommand;
use AmjadIqbal\LogPulse\Commands\LogPulseSimulateCommand;
use AmjadIqbal\LogPulse\Commands\LogPulseStatusCommand;
use AmjadIqbal\LogPulse\Listeners\LogEventHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LogPulseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/logpulse.php',
            'logpulse'
        );

        // Register singleton services
        $this->app->singleton('logpulse', function (Application $app) {
            return new LogPulseManager($app);
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->publishViews();
        $this->registerCommands();
        $this->registerRoutes();
        $this->registerEventListeners();
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/logpulse.php' => config_path('logpulse.php'),
        ], 'logpulse-config');
    }

    /**
     * Publish database migrations.
     */
    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'logpulse-migrations');
    }

    /**
     * Publish views for the dashboard.
     */
    protected function publishViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'logpulse');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/logpulse'),
        ], 'logpulse-views');
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LogPulseInstallCommand::class,
                LogPulseMonitorCommand::class,
                LogPulseQuickstartCommand::class,
                LogPulseSimulateCommand::class,
                LogPulseStatusCommand::class,
            ]);
        }
    }

    /**
     * Register dashboard routes.
     */
    protected function registerRoutes(): void
    {
        if (config('logpulse.dashboard.enabled', true)) {
            Route::middleware(config('logpulse.dashboard.middleware', ['web', 'auth']))
                ->prefix(config('logpulse.dashboard.path', '/logpulse'))
                ->group(function () {
                    $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
                });
        }
    }

    /**
     * Register event listeners for log monitoring.
     */
    protected function registerEventListeners(): void
    {
        // Listen to Laravel's log events
        Event::listen(MessageLogged::class, LogEventHandler::class);

        // Listen to exception events
        Event::listen(\Illuminate\Foundation\Events\ExceptionHandlerReported::class, 
            Listeners\ExceptionReportedHandler::class
        );
    }
}