<?php

// routes/web.php

use AmjadIqbal\LogPulse\Dashboard\Http\Controllers\LogPulseDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('logpulse.dashboard.middleware', ['web', 'auth']))
    ->prefix(config('logpulse.dashboard.path', '/logpulse'))
    ->group(function () {
        
        // Main dashboard
        Route::get('/', [LogPulseDashboardController::class, 'index'])
            ->name('logpulse.dashboard');
        
        // API endpoints for real-time data
        Route::get('/api/status', [LogPulseDashboardController::class, 'status'])
            ->name('logpulse.api.status');
        
        Route::get('/api/events', [LogPulseDashboardController::class, 'events'])
            ->name('logpulse.api.events');
        
        Route::get('/api/events/{id}', [LogPulseDashboardController::class, 'show'])
            ->name('logpulse.api.events.show');
        
        Route::get('/api/charts/error-rate', [LogPulseDashboardController::class, 'errorRateChart'])
            ->name('logpulse.api.charts.error-rate');
        
        Route::get('/api/charts/severity', [LogPulseDashboardController::class, 'severityChart'])
            ->name('logpulse.api.charts.severity');
        
        Route::post('/api/circuit-breaker/reset', [LogPulseDashboardController::class, 'resetCircuitBreaker'])
            ->name('logpulse.api.circuit-breaker.reset');
        
        Route::delete('/api/events/purge', [LogPulseDashboardController::class, 'purgeEvents'])
            ->name('logpulse.api.events.purge');
    });