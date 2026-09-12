<?php

// src/AlertChannels/WebhookChannel.php

namespace AmjadIqbal\LogPulse\AlertChannels;

use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    protected string $webhookUrl;

    protected array $config;

    protected array $headers;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->webhookUrl = $config['custom_webhook_url'] ?? config('logpulse.custom_webhook_url');
        $this->headers = $config['headers'] ?? [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Laravel-LogPulse/1.0',
        ];
    }

    public function send(LogPulseEvent $event, string $severity): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('LogPulse: Custom webhook URL not configured');

            return false;
        }

        $payload = $this->buildPayload($event, $severity);

        try {
            $response = Http::timeout(10)
                ->withHeaders($this->headers)
                ->post($this->webhookUrl, $payload);

            if ($response->successful()) {
                Log::info("LogPulse: Custom webhook alert sent for {$event->exception_class}");

                return true;
            }

            Log::error("LogPulse: Custom webhook failed — {$response->status()}: {$response->body()}");

            return false;
        } catch (\Exception $e) {
            Log::error("LogPulse: Custom webhook exception — {$e->getMessage()}");

            return false;
        }
    }

    protected function buildPayload(LogPulseEvent $event, string $severity): array
    {
        return [
            'source' => 'laravel-logpulse',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'alert' => [
                'severity' => $severity,
                'exception_class' => $event->exception_class,
                'message' => $event->message,
                'route' => $event->route,
                'file' => $event->file,
                'line' => $event->line,
                'occurrence_count' => $event->occurrence_count,
                'first_seen_at' => $event->first_seen_at->toIso8601String(),
                'last_seen_at' => $event->last_seen_at->toIso8601String(),
                'stack_trace' => $event->stack_trace,
                'context' => $event->context,
                'request_data' => $event->request_data,
            ],
            'dashboard_url' => config('app.url').config('logpulse.dashboard.path', '/logpulse'),
        ];
    }
}
