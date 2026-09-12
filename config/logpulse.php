<?php

// config/logpulse.php

return [
    /*
    |--------------------------------------------------------------------------
    | Alert Channels
    |--------------------------------------------------------------------------
    |
    | Configure which notification channels LogPulse should use to send alerts.
    | Supported channels: slack, discord, mail, webhook, vonage
    |
    */
    'channels' => explode(',', env('LOGPULSE_CHANNELS', 'slack,mail')),

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    */
    'slack_webhook_url' => env('LOGPULSE_SLACK_WEBHOOK'),
    'discord_webhook_url' => env('LOGPULSE_DISCORD_WEBHOOK'),
    'alert_email' => env('LOGPULSE_ALERT_EMAIL', 'oncall@example.com'),
    'custom_webhook_url' => env('LOGPULSE_CUSTOM_WEBHOOK'),

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    |
    | Define when alerts should trigger based on error count within a rolling
    | time window. Each severity level can have different thresholds.
    |
    */
    'thresholds' => [
        'critical' => [
            'count' => (int) env('LOGPULSE_CRITICAL_COUNT', 10),
            'window' => (int) env('LOGPULSE_CRITICAL_WINDOW', 5),
        ],
        'warning' => [
            'count' => (int) env('LOGPULSE_WARNING_COUNT', 50),
            'window' => (int) env('LOGPULSE_WARNING_WINDOW', 15),
        ],
        'info' => [
            'count' => (int) env('LOGPULSE_INFO_COUNT', 100),
            'window' => (int) env('LOGPULSE_INFO_WINDOW', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Preset
    |--------------------------------------------------------------------------
    |
    | Pre-configured optimizations for common application types.
    | Options: saas-webhook, ecommerce, api-gateway, cms-blog, custom
    |
    */
    'preset' => env('LOGPULSE_PRESET', 'saas-webhook'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => env('LOGPULSE_DASHBOARD', true),
        'path' => env('LOGPULSE_DASHBOARD_PATH', '/logpulse'),
        'middleware' => ['web', 'auth'],
        'whitelisted_ips' => explode(',', env('LOGPULSE_WHITELISTED_IPS', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Driver for storing event history. Options: database, redis, file
    |
    */
    'storage' => [
        'driver' => env('LOGPULSE_STORAGE_DRIVER', 'database'),
        'table' => env('LOGPULSE_TABLE_NAME', 'logpulse_events'),
        'pruning_days' => (int) env('LOGPULSE_PRUNING_DAYS', 30),
        'redis_connection' => env('LOGPULSE_REDIS_CONNECTION', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker (Self-Protection)
    |--------------------------------------------------------------------------
    |
    | Prevents LogPulse from overwhelming your notification channels during
    | an error storm. Once the limit is reached, alerts are suppressed.
    |
    */
    'circuit_breaker' => [
        'enabled' => env('LOGPULSE_CIRCUIT_BREAKER', true),
        'max_alerts_per_minute' => (int) env('LOGPULSE_MAX_ALERTS', 5),
        'cooldown_minutes' => (int) env('LOGPULSE_COOLDOWN', 15),
        'backoff_multiplier' => (float) env('LOGPULSE_BACKOFF_MULTIPLIER', 2.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exception classes that should never trigger alerts. These are typically
    | expected exceptions that don't indicate system problems.
    |
    */
    'ignore_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Routes
    |--------------------------------------------------------------------------
    |
    | Route patterns to exclude from monitoring. Useful for health-check
    | endpoints and internal routes that you don't want to monitor.
    |
    */
    'ignore_routes' => [
        'horizon/*',
        'telescope/*',
        '_debugbar/*',
        'health-check',
        'health',
    ],

    /*
    |--------------------------------------------------------------------------
    | System Burden Score Calculation Weights
    |--------------------------------------------------------------------------
    |
    | Adjust how the System Burden Score is calculated. The score combines
    | error severity, frequency, and resource impact.
    |
    */
    'burden_score' => [
        'severity_weight' => 0.3,
        'frequency_weight' => 0.4,
        'resource_impact_weight' => 0.3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Control how LogPulse handles high-frequency events to prevent
    | performance degradation.
    |
    */
    'rate_limiting' => [
        'max_events_per_second' => (int) env('LOGPULSE_RATE_LIMIT', 100),
        'burst_size' => (int) env('LOGPULSE_BURST_SIZE', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Adaptive Baseline (Pro Feature)
    |--------------------------------------------------------------------------
    |
    | When enabled, LogPulse learns normal error patterns over time and
    | adjusts thresholds automatically.
    |
    */
    'adaptive_baseline' => [
        'enabled' => env('LOGPULSE_ADAPTIVE', false),
        'learning_period_days' => 7,
        'sensitivity' => 0.8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Grouping
    |--------------------------------------------------------------------------
    |
    | Group similar alerts to reduce notification noise.
    |
    */
    'grouping' => [
        'enabled' => true,
        'group_by' => ['exception_class', 'route'],
        'max_group_size' => 5,
    ],
];