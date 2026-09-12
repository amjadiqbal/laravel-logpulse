<?php

// src/Commands/LogPulseStatusCommand.php

namespace AmjadIqbal\LogPulse\Commands;

use Illuminate\Console\Command;
use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use AmjadIqbal\LogPulse\Analyzers\BurdenCalculator;
use AmjadIqbal\LogPulse\Analyzers\FrequencyAnalyzer;
use function Laravel\Prompts\{table, info, warning, error};

class LogPulseStatusCommand extends Command
{
    protected $signature = 'logpulse:status
                            {--json : Output as JSON for scripting}';
    protected $description = 'Display current LogPulse system status';

    public function handle(): void
    {
        if ($this->option('json')) {
            $this->outputJson();
            return;
        }

        $this->displayStatus();
    }

    protected function displayStatus(): void
    {
        $this->info('🫀 LogPulse Status Report');
        $this->info('═'.str_repeat('═', 50));
        $this->newLine();

        // Configuration Status
        $this->displayConfigurationStatus();
        
        // Event Statistics
        $this->displayEventStatistics();
        
        // Alert Channel Status
        $this->displayChannelStatus();
        
        // Circuit Breaker Status
        $this->displayCircuitBreakerStatus();
        
        // Quick Health Check
        $this->displayQuickHealth();
    }

    protected function displayConfigurationStatus(): void
    {
        $this->info('📋 Configuration');
        $this->line(str_repeat('─', 40));

        $config = config('logpulse');
        
        table(
            headers: ['Setting', 'Value'],
            rows: [
                ['Preset', $config['preset'] ?? 'custom'],
                ['Channels', implode(', ', $config['channels'] ?? ['none'])],
                ['Dashboard', ($config['dashboard']['enabled'] ?? false) ? 'Enabled' : 'Disabled'],
                ['Storage', $config['storage']['driver'] ?? 'database'],
                ['Circuit Breaker', ($config['circuit_breaker']['enabled'] ?? false) ? 'Active' : 'Inactive'],
                ['Critical Threshold', ($config['thresholds']['critical']['count'] ?? '?') . ' errors / ' . ($config['thresholds']['critical']['window'] ?? '?') . 'min'],
            ]
        );
        
        $this->newLine();
    }

    protected function displayEventStatistics(): void
    {
        $this->info('📊 Event Statistics');
        $this->line(str_repeat('─', 40));

        $totalEvents = LogPulseEvent::count();
        $todayEvents = LogPulseEvent::whereDate('created_at', today())->count();
        $criticalEvents = LogPulseEvent::where('severity', 'critical')
            ->whereDate('created_at', today())
            ->count();

        table(
            headers: ['Metric', 'Value'],
            rows: [
                ['Total Events', number_format($totalEvents)],
                ['Events Today', number_format($todayEvents)],
                ['Critical Today', $criticalEvents > 0 ? "<fg=red>{$criticalEvents}</>" : '0'],
                ['Events Last Hour', LogPulseEvent::where('created_at', '>=', now()->subHour())->count()],
                ['Unique Exceptions', LogPulseEvent::distinct('exception_class')->count()],
            ]
        );

        $this->newLine();
    }

    protected function displayChannelStatus(): void
    {
        $this->info('🔔 Alert Channels');
        $this->line(str_repeat('─', 40));

        $channels = config('logpulse.channels', []);
        $rows = [];

        foreach ($channels as $channel) {
            $configured = $this->isChannelConfigured($channel);
            $rows[] = [
                strtoupper($channel),
                $configured ? '<fg=green>● Configured</>' : '<fg=red>● Missing Config</>',
                $configured ? 'Ready' : 'Check .env file',
            ];
        }

        if (empty($rows)) {
            $rows[] = ['None', '—', 'No channels configured'];
        }

        table(
            headers: ['Channel', 'Status', 'Info'],
            rows: $rows
        );

        $this->newLine();
    }

    protected function displayCircuitBreakerStatus(): void
    {
        $this->info('🛡️ Circuit Breaker');
        $this->line(str_repeat('─', 40));

        $breakerConfig = config('logpulse.circuit_breaker', []);
        
        table(
            headers: ['Setting', 'Value'],
            rows: [
                ['Status', ($breakerConfig['enabled'] ?? false) ? '<fg=green>Active</>' : '<fg=yellow>Inactive</>'],
                ['Max Alerts/Min', $breakerConfig['max_alerts_per_minute'] ?? 'N/A'],
                ['Cooldown', ($breakerConfig['cooldown_minutes'] ?? 'N/A') . ' minutes'],
                ['Backoff', ($breakerConfig['backoff_multiplier'] ?? 'N/A') . 'x'],
            ]
        );

        $this->newLine();
    }

    protected function displayQuickHealth(): void
    {
        $this->info('🏥 Quick Health Check');
        $this->line(str_repeat('─', 40));

        $events = LogPulseEvent::where('last_seen_at', '>=', now()->subMinutes(30))->get();
        $calculator = new BurdenCalculator();
        $score = $calculator->calculate($events);
        $interpretation = $calculator->getInterpretation($score);

        $this->line("  Burden Score: {$score}% — {$interpretation['emoji']} {$interpretation['text']}");
        $this->line("  Action: {$interpretation['action']}");
        
        $this->newLine();

        // Top exceptions warning
        $analyzer = new FrequencyAnalyzer();
        $topExceptions = $analyzer->getTopExceptions(3, 5);

        if ($topExceptions->isNotEmpty()) {
            $this->line('  Top errors (last 5 min):');
            foreach ($topExceptions as $exception) {
                $icon = $exception['trend_icon'];
                $this->line("    {$icon} {$exception['exception_class']} — {$exception['count']} occurrences");
            }
        }
    }

    protected function isChannelConfigured(string $channel): bool
    {
        return match($channel) {
            'slack' => !empty(config('logpulse.slack_webhook_url')),
            'discord' => !empty(config('logpulse.discord_webhook_url')),
            'mail' => !empty(config('logpulse.alert_email')),
            'webhook' => !empty(config('logpulse.custom_webhook_url')),
            default => false,
        };
    }

    protected function outputJson(): void
    {
        $events = LogPulseEvent::where('last_seen_at', '>=', now()->subMinutes(30))->get();
        $calculator = new BurdenCalculator();
        
        $status = [
            'timestamp' => now()->toIso8601String(),
            'burden_score' => $calculator->calculate($events),
            'total_events' => LogPulseEvent::count(),
            'events_today' => LogPulseEvent::whereDate('created_at', today())->count(),
            'configured_channels' => config('logpulse.channels', []),
            'circuit_breaker_active' => config('logpulse.circuit_breaker.enabled', false),
            'preset' => config('logpulse.preset', 'custom'),
        ];

        $this->line(json_encode($status, JSON_PRETTY_PRINT));
    }
}