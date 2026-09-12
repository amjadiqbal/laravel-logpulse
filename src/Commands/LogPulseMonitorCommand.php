<?php

// src/Commands/LogPulseMonitorCommand.php

namespace AmjadIqbal\LogPulse\Commands;

use Illuminate\Console\Command;
use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use function Laravel\Prompts\{info, warning, error, table, spin};

class LogPulseMonitorCommand extends Command
{
    protected $signature = 'logpulse:monitor
                            {--refresh=3 : Refresh interval in seconds}
                            {--top=10 : Number of top errors to show}';
    
    protected $description = 'Real-time log monitoring dashboard in the terminal';

    public function handle(): void
    {
        $this->info('🫀 LogPulse Real-Time Monitor');
        $this->info('Press Ctrl+C to exit');
        $this->newLine();

        while (true) {
            $this->refreshDisplay();
            sleep((int) $this->option('refresh'));
        }
    }

    private function refreshDisplay(): void
    {
        // Clear screen (cross-platform)
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }

        $this->renderHeader();
        $this->renderBurdenScore();
        $this->renderTopErrors();
        $this->renderAlertStatus();
        $this->renderFooter();
    }

    private function renderHeader(): void
    {
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║         🫀 LARAVEL LOGPULSE — LIVE MONITOR               ║');
        $this->line('╠══════════════════════════════════════════════════════════╣');
        
        $now = now()->format('Y-m-d H:i:s');
        $eventsCount = LogPulseEvent::count();
        
        $this->line("║  🕐 {$now}    📊 Total Events: {$eventsCount}");
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function renderBurdenScore(): void
    {
        // Calculate burden score
        $score = rand(10, 95); // In real implementation, calculate from actual data
        
        $color = match(true) {
            $score > 80 => 'red',
            $score > 50 => 'yellow',
            default => 'green',
        };

        $bar = $this->progressBar($score);
        
        $this->line("🔥 System Burden Score: {$bar} {$score}%");
        
        $status = match(true) {
            $score > 80 => '<fg=red>CRITICAL — System under heavy stress</>',
            $score > 50 => '<fg=yellow>WARNING — Elevated error rates</>',
            default => '<fg=green>HEALTHY — Normal operating levels</>',
        };
        
        $this->line("   Status: {$status}");
        $this->newLine();
    }

    private function renderTopErrors(): void
    {
        $top = (int) $this->option('top');
        
        $this->line('📈 Top Errors (Last 5 Minutes):');
        $this->line(str_repeat('─', 60));

        // Simulated data — replace with actual DB queries
        $errors = [
            ['exception' => 'PDOException', 'count' => 47, 'route' => '/api/checkout', 'trend' => '↑'],
            ['exception' => 'GuzzleHttp Timeout', 'count' => 23, 'route' => '/webhook/stripe', 'trend' => '→'],
            ['exception' => 'ValidationException', 'count' => 15, 'route' => '/api/register', 'trend' => '↓'],
            ['exception' => 'TokenMismatchException', 'count' => 8, 'route' => '/login', 'trend' => '↑'],
            ['exception' => 'Swift_TransportException', 'count' => 5, 'route' => '/password/email', 'trend' => '→'],
        ];

        $rows = [];
        foreach (array_slice($errors, 0, $top) as $error) {
            $countBar = str_repeat('█', min($error['count'], 20));
            $rows[] = [
                $error['exception'],
                "{$countBar} ({$error['count']})",
                $error['route'],
                $error['trend'] === '↑' ? '<fg=red>↑ Increasing</>' : 
                    ($error['trend'] === '↓' ? '<fg=green>↓ Decreasing</>' : '<fg=yellow>→ Stable</>'),
            ];
        }

        table(
            headers: ['Exception', 'Count', 'Route', 'Trend'],
            rows: $rows
        );

        $this->newLine();
    }

    private function renderAlertStatus(): void
    {
        $this->line('🔔 Alert Channels Status:');
        $this->line(str_repeat('─', 60));
        
        $channels = config('logpulse.channels', ['slack', 'mail']);
        $statuses = [];
        
        foreach ($channels as $channel) {
            $connected = rand(0, 1); // Simulated — check actual connection
            $statuses[] = [
                strtoupper($channel),
                $connected ? '<fg=green>● Connected</>' : '<fg=red>● Disconnected</>',
                $connected ? 'Last alert: 2 min ago' : 'Connection failed',
            ];
        }
        
        table(
            headers: ['Channel', 'Status', 'Info'],
            rows: $statuses
        );
        
        $this->newLine();
    }

    private function renderFooter(): void
    {
        $this->line(str_repeat('─', 60));
        $refresh = $this->option('refresh');
        $this->line("<fg=gray>🔄 Refreshing every {$refresh}s | Press Ctrl+C to exit | v1.0.0</>");
    }

    private function progressBar(int $percentage): string
    {
        $width = 20;
        $filled = round(($percentage / 100) * $width);
        $empty = $width - $filled;

        $color = match(true) {
            $percentage > 80 => 'red',
            $percentage > 50 => 'yellow',
            default => 'green',
        };

        return "<fg={$color}>" . str_repeat('█', $filled) . str_repeat('░', $empty) . '</>';
    }
}