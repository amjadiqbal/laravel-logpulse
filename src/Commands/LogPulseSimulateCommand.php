<?php

// src/Commands/LogPulseSimulateCommand.php

namespace AmjadIqbal\LogPulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class LogPulseSimulateCommand extends Command
{
    protected $signature = 'logpulse:simulate';

    protected $description = 'Simulate error patterns to test LogPulse alerting';

    public function handle(): void
    {
        info('🧪 LogPulse Error Simulator');
        info('Generate test errors to verify your alert configuration');

        $scenario = select(
            label: 'Choose a simulation scenario',
            options: [
                'burst' => '💥 Error Burst — Rapid 50 errors in 10 seconds',
                'cascade' => '🌊 Cascade Failure — Growing error rate over 1 minute',
                'single' => '🎯 Single Critical Error — One high-severity exception',
                'pattern' => '📊 Pattern Test — Repeating error every 10s for 2 minutes',
            ],
            default: 'burst'
        );

        $confirmed = confirm(
            label: 'This will generate REAL log entries. Continue?',
            default: true,
            hint: 'Your configured alert channels may receive notifications'
        );

        if (! $confirmed) {
            warning('Simulation cancelled.');

            return;
        }

        // select()'s return type is string|int in general, so an unmatched-value default
        // is required for the match to be provably exhaustive.
        match ($scenario) {
            'burst' => $this->simulateBurst(),
            'cascade' => $this->simulateCascade(),
            'single' => $this->simulateSingle(),
            'pattern' => $this->simulatePattern(),
            default => throw new \InvalidArgumentException("Unknown simulation scenario: {$scenario}"),
        };

        info('✅ Simulation complete! Check your alert channels.');
    }

    private function simulateBurst(): void
    {
        spin(
            message: 'Generating error burst...',
            callback: function () {
                for ($i = 0; $i < 50; $i++) {
                    Log::error('Simulated PDOException: Connection refused', [
                        'exception' => 'PDOException',
                        'file' => 'Database/Connection.php',
                        'line' => rand(100, 500),
                        'logpulse_simulated' => true,
                    ]);
                    usleep(200000); // 0.2 seconds between each
                }
            }
        );
    }

    private function simulateCascade(): void
    {
        $delays = [100000, 200000, 400000, 800000, 1600000]; // Microseconds

        foreach ($delays as $delay) {
            for ($i = 0; $i < 10; $i++) {
                Log::error('Simulated GuzzleHttp Timeout: API endpoint unreachable', [
                    'exception' => 'GuzzleHttp\Exception\ConnectException',
                    'logpulse_simulated' => true,
                ]);
                usleep($delay);
            }
        }
    }

    private function simulateSingle(): void
    {
        Log::critical('CRITICAL: Payment gateway encryption key expired!', [
            'exception' => 'EncryptionKeyExpiredException',
            'severity' => 'critical',
            'service' => 'payment-gateway',
            'logpulse_simulated' => true,
        ]);
    }

    private function simulatePattern(): void
    {
        for ($i = 0; $i < 12; $i++) {
            Log::error('Simulated Redis connection timeout — retry '.($i + 1), [
                'exception' => 'Predis\Connection\ConnectionException',
                'logpulse_simulated' => true,
            ]);
            sleep(10);
        }
    }
}
