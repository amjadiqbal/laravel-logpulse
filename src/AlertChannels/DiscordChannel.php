<?php

// src/AlertChannels/DiscordChannel.php

namespace AmjadIqbal\LogPulse\AlertChannels;

use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordChannel
{
    protected string $webhookUrl;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->webhookUrl = $config['discord_webhook_url'] ?? config('logpulse.discord_webhook_url');
    }

    public function send(LogPulseEvent $event, string $severity): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('LogPulse: Discord webhook URL not configured');
            return false;
        }

        $payload = $this->buildPayload($event, $severity);

        try {
            $response = Http::timeout(5)
                ->post($this->webhookUrl, $payload);

            if ($response->successful() || $response->status() === 204) {
                Log::info("LogPulse: Discord alert sent for {$event->exception_class}");
                return true;
            }

            Log::error("LogPulse: Discord alert failed — {$response->status()}: {$response->body()}");
            return false;
        } catch (\Exception $e) {
            Log::error("LogPulse: Discord alert exception — {$e->getMessage()}");
            return false;
        }
    }

    protected function buildPayload(LogPulseEvent $event, string $severity): array
    {
        $color = match($severity) {
            'critical' => 0xFF0000,
            'warning' => 0xFFA500,
            'info' => 0x36A64F,
            default => 0xCCCCCC,
        };

        $emoji = match($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '📊',
        };

        $stackTrace = $this->formatStackTrace($event->stack_trace);

        return [
            'embeds' => [
                [
                    'title' => "{$emoji} LOGPULSE ALERT — " . strtoupper($severity),
                    'description' => $event->message,
                    'color' => $color,
                    'fields' => [
                        [
                            'name' => 'Exception',
                            'value' => "`{$event->exception_class}`",
                            'inline' => true,
                        ],
                        [
                            'name' => 'Occurrences',
                            'value' => "**{$event->occurrence_count}** times",
                            'inline' => true,
                        ],
                        [
                            'name' => 'Route',
                            'value' => "`{$event->route}`",
                            'inline' => true,
                        ],
                        [
                            'name' => 'Location',
                            'value' => "`{$event->file}:{$event->line}`",
                            'inline' => true,
                        ],
                        [
                            'name' => 'First Seen',
                            'value' => $event->first_seen_at->diffForHumans(),
                            'inline' => true,
                        ],
                        [
                            'name' => 'Last Seen',
                            'value' => $event->last_seen_at->diffForHumans(),
                            'inline' => true,
                        ],
                        [
                            'name' => 'Stack Trace (truncated)',
                            'value' => "```php\n{$stackTrace}\n```",
                            'inline' => false,
                        ],
                    ],
                    'footer' => [
                        'text' => 'Laravel LogPulse • ' . config('app.name'),
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ];
    }

    protected function formatStackTrace(?string $stackTrace): string
    {
        if (empty($stackTrace)) {
            return 'No stack trace available';
        }

        $lines = explode("\n", $stackTrace);
        return implode("\n", array_slice($lines, 0, 5));
    }
}