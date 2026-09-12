<?php

// src/Listeners/LogEventHandler.php

namespace AmjadIqbal\LogPulse\Listeners;

use AmjadIqbal\LogPulse\AlertChannels\DiscordChannel;
use AmjadIqbal\LogPulse\AlertChannels\SlackChannel;
use AmjadIqbal\LogPulse\AlertChannels\WebhookChannel;
use AmjadIqbal\LogPulse\Analyzers\BurdenCalculator;
use AmjadIqbal\LogPulse\Analyzers\FrequencyAnalyzer;
use AmjadIqbal\LogPulse\Analyzers\PatternMatcher;
use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogEventHandler
{
    protected array $rateLimitCache = [];

    protected FrequencyAnalyzer $frequencyAnalyzer;

    protected PatternMatcher $patternMatcher;

    protected BurdenCalculator $burdenCalculator;

    public function __construct()
    {
        $this->frequencyAnalyzer = new FrequencyAnalyzer;
        $this->patternMatcher = new PatternMatcher;
        $this->burdenCalculator = new BurdenCalculator;
    }

    /**
     * Handle the log event.
     */
    public function handle(MessageLogged $event): void
    {
        // Skip if rate limited
        if (! $this->checkRateLimit()) {
            return;
        }

        // Ignore non-error levels (configurable)
        if (! in_array($event->level, ['error', 'critical', 'alert', 'emergency'])) {
            return;
        }

        // Extract exception information
        $exceptionData = $this->extractExceptionData($event);

        // Skip ignored exceptions
        if ($this->isIgnoredException($exceptionData['exception_class'] ?? '')) {
            return;
        }

        // Skip ignored routes
        if ($this->isIgnoredRoute($exceptionData['route'] ?? '')) {
            return;
        }

        // Store or update the event
        $logEvent = $this->storeOrUpdateEvent($exceptionData, $event->level);

        // Check thresholds and send alerts if needed
        $this->checkAndAlert($logEvent);
    }

    /**
     * Extract exception data from the log event.
     */
    protected function extractExceptionData(MessageLogged $event): array
    {
        $context = $event->context;

        $exceptionClass = 'UnknownException';
        $file = null;
        $line = null;
        $stackTrace = null;

        // Try to extract from context
        if (isset($context['exception'])) {
            if (is_object($context['exception'])) {
                $exception = $context['exception'];
                $exceptionClass = get_class($exception);
                $file = $exception->getFile();
                $line = $exception->getLine();
                $stackTrace = $exception->getTraceAsString();
            } elseif (is_string($context['exception'])) {
                $exceptionClass = $context['exception'];
            }
        }

        // Extract route from context or request
        $route = $context['route'] ?? null;
        if (! $route && app()->bound('request')) {
            $request = app('request');
            $route = $request->path();
        }

        return [
            'exception_class' => $exceptionClass,
            'message' => $event->message,
            'route' => $route,
            'file' => $file ?? ($context['file'] ?? null),
            'line' => $line ?? ($context['line'] ?? null),
            'stack_trace' => $stackTrace,
            'context' => array_filter($context, fn ($key) => ! in_array($key, ['exception', 'file', 'line']), ARRAY_FILTER_USE_KEY),
            'request_data' => $this->sanitizeRequestData(),
        ];
    }

    /**
     * Store a new event or update an existing one.
     */
    protected function storeOrUpdateEvent(array $data, string $level): LogPulseEvent
    {
        $aggregateId = $this->generateAggregateId($data['exception_class'], $data['route']);

        $existing = LogPulseEvent::where('aggregate_id', $aggregateId)
            ->where('last_seen_at', '>=', now()->subHour())
            ->first();

        if ($existing) {
            $existing->update([
                'occurrence_count' => $existing->occurrence_count + 1,
                'last_seen_at' => now(),
                'message' => $data['message'],
                'context' => $data['context'],
            ]);

            return $existing->fresh();
        }

        return LogPulseEvent::create([
            'aggregate_id' => $aggregateId,
            'exception_class' => $data['exception_class'],
            'severity' => $this->mapLevelToSeverity($level),
            'message' => $data['message'],
            'route' => $data['route'],
            'file' => $data['file'],
            'line' => $data['line'],
            'stack_trace' => $data['stack_trace'],
            'context' => $data['context'],
            'request_data' => $data['request_data'],
            'occurrence_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Check thresholds and send alerts if necessary.
     */
    protected function checkAndAlert(LogPulseEvent $event): void
    {
        // Check if circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            return;
        }

        $analysis = $this->frequencyAnalyzer->analyze(
            $event->exception_class,
            $event->route
        );

        foreach ($analysis as $severity => $result) {
            if ($result['triggered']) {
                $this->sendAlerts($event, $severity, $result);

                // Mark as alerted
                $event->update([
                    'alert_status' => 'sent',
                    'alerted_at' => now(),
                    'alert_channel' => implode(',', config('logpulse.channels', [])),
                ]);

                // Only alert once per event aggregate
                break;
            }
        }
    }

    /**
     * Send alerts to configured channels.
     */
    protected function sendAlerts(LogPulseEvent $event, string $severity, array $result): void
    {
        $channels = config('logpulse.channels', []);

        foreach ($channels as $channel) {
            try {
                $sent = match ($channel) {
                    'slack' => (new SlackChannel)->send($event, $severity),
                    'discord' => (new DiscordChannel)->send($event, $severity),
                    'webhook' => (new WebhookChannel)->send($event, $severity),
                    default => false,
                };

                if ($sent) {
                    $this->recordAlert($channel, $severity, $event);
                }
            } catch (\Exception $e) {
                Log::error("LogPulse: Failed to send {$channel} alert: {$e->getMessage()}");
            }
        }
    }

    /**
     * Record alert in history.
     */
    protected function recordAlert(string $channel, string $severity, LogPulseEvent $event): void
    {
        DB::table('logpulse_alerts')->insert([
            'channel' => $channel,
            'severity' => $severity,
            'message' => $event->message,
            'metadata' => json_encode([
                'exception_class' => $event->exception_class,
                'route' => $event->route,
                'occurrence_count' => $event->occurrence_count,
            ]),
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Check rate limiting.
     */
    protected function checkRateLimit(): bool
    {
        $maxPerSecond = config('logpulse.rate_limiting.max_events_per_second', 100);
        $second = now()->format('YmdHis');

        if (! isset($this->rateLimitCache[$second])) {
            $this->rateLimitCache = [$second => 0];
        }

        $this->rateLimitCache[$second]++;

        return $this->rateLimitCache[$second] <= $maxPerSecond;
    }

    /**
     * Check if circuit breaker is open.
     */
    protected function isCircuitBreakerOpen(): bool
    {
        if (! config('logpulse.circuit_breaker.enabled', true)) {
            return false;
        }

        $breaker = DB::table('logpulse_circuit_breaker')
            ->where('channel', 'global')
            ->first();

        if (! $breaker || ! $breaker->is_open) {
            return false;
        }

        if ($breaker->cooldown_until && now()->lt($breaker->cooldown_until)) {
            return true;
        }

        // Cooldown expired, reset breaker
        DB::table('logpulse_circuit_breaker')
            ->where('channel', 'global')
            ->update([
                'is_open' => false,
                'alert_count' => 0,
            ]);

        return false;
    }

    /**
     * Check if exception should be ignored.
     */
    protected function isIgnoredException(string $exceptionClass): bool
    {
        $ignoredExceptions = config('logpulse.ignore_exceptions', []);

        foreach ($ignoredExceptions as $ignored) {
            if ($exceptionClass === $ignored || Str::is($ignored, $exceptionClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if route should be ignored.
     */
    protected function isIgnoredRoute(string $route): bool
    {
        $ignoredRoutes = config('logpulse.ignore_routes', []);

        foreach ($ignoredRoutes as $pattern) {
            if (Str::is($pattern, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate aggregate ID for grouping similar events.
     */
    protected function generateAggregateId(string $exceptionClass, ?string $route): string
    {
        return md5($exceptionClass.'|'.($route ?? 'no-route'));
    }

    /**
     * Map log level to severity.
     */
    protected function mapLevelToSeverity(string $level): string
    {
        return match ($level) {
            'emergency', 'alert', 'critical' => 'critical',
            'error' => 'error',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            default => 'debug',
        };
    }

    /**
     * Sanitize request data to remove sensitive information.
     */
    protected function sanitizeRequestData(): ?array
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        $data = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Include input but redact sensitive fields
        $input = $request->except([
            'password', 'password_confirmation', 'current_password',
            'token', 'api_key', 'secret', 'credit_card', 'ssn',
        ]);

        if (! empty($input)) {
            $data['input'] = $input;
        }

        return $data;
    }
}
