<?php

// src/AlertChannels/SlackChannel.php

namespace AmjadIqbal\LogPulse\AlertChannels;

use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackChannel
{
    protected string $webhookUrl;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->webhookUrl = $config['slack_webhook_url'] ?? config('logpulse.slack_webhook_url');
    }

    public function send(LogPulseEvent $event, string $severity): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('LogPulse: Slack webhook URL not configured');
            return false;
        }

        $payload = $this->buildPayload($event, $severity);

        try {
            $response = Http::timeout(5)
                ->post($this->webhookUrl, $payload);

            if ($response->successful()) {
                Log::info("LogPulse: Slack alert sent for {$event->exception_class}");
                return true;
            }

            Log::error("LogPulse: Slack alert failed — {$response->status()}: {$response->body()}");
            return false;
        } catch (\Exception $e) {
            Log::error("LogPulse: Slack alert exception — {$e->getMessage()}");
            return false;
        }
    }

    protected function buildPayload(LogPulseEvent $event, string $severity): array
    {
        $color = match($severity) {
            'critical' => '#FF0000',
            'warning' => '#FFA500',
            'info' => '#36A64F',
            default => '#CCCCCC',
        };

        $emoji = match($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📊',
        };

        $stackTrace = $this->formatStackTrace($event->stack_trace);
        $route = $event->route ?? 'N/A';
        $count = $event->occurrence_count;

        return [
            'attachments' => [
                [
                    'color' => $color,
                    'pretext' => "{$emoji} *LOGPULSE ALERT — " . strtoupper($severity) . "*",
                    'title' => $event->exception_class,
                    'title_link' => config('app.url') . config('logpulse.dashboard.path', '/logpulse'),
                    'text' => "_{$event->message}_",
                    'fields' => [
                        [
                            'title' => 'Occurrences',
                            'value' => "{$count} times in the last window",
                            'short' => true,
                        ],
                        [
                            'title' => 'Route',
                            'value' => "`{$route}`",
                            'short' => true,
                        ],
                        [
                            'title' => 'File',
                            'value' => "`{$event->file}:{$event->line}`",
                            'short' => true,
                        ],
                        [
                            'title' => 'First Seen',
                            'value' => $event->first_seen_at->diffForHumans(),
                            'short' => true,
                        ],
                        [
                            'title' => 'Stack Trace',
                            'value' => "```{$stackTrace}```",
                            'short' => false,
                        ],
                    ],
                    'footer' => 'Laravel LogPulse',
                    'footer_icon' => 'https://laravel.com/img/logomark.min.svg',
                    'ts' => now()->timestamp,
                ],
            ],
        ];
    }

    protected function formatStackTrace(?string $stackTrace): string
    {
        if (empty($stackTrace)) {
            return 'No stack trace available';
        }

        // Take first 5 lines of stack trace for readability
        $lines = explode("\n", $stackTrace);
        return implode("\n", array_slice($lines, 0, 5));
    }
}