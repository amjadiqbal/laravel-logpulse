<?php

// src/Commands/LogPulseQuickstartCommand.php

namespace AmjadIqbal\LogPulse\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\{intro, outro, info, note, select, text, spin, progress};

class LogPulseQuickstartCommand extends Command
{
    protected $signature = 'logpulse:quickstart';
    protected $description = 'Quick-start wizard for common LogPulse configurations';

    public function handle(): int
    {
        intro('🚀 LogPulse Quickstart');
        note('Answer a few questions to get LogPulse configured for your needs.');

        $appType = select(
            label: 'What type of application are you monitoring?',
            options: [
                'api' => '🔌 REST/GraphQL API',
                'web' => '🌐 Traditional Web App (Blade/Livewire)',
                'spa' => '⚛️ SPA Backend (Vue/React + API)',
                'microservice' => '🔧 Microservice / Internal Service',
                'ecommerce' => '🛒 E-Commerce Application',
            ],
            default: 'web'
        );

        $teamSize = select(
            label: 'How large is your development team?',
            options: [
                'solo' => '👤 Solo Developer',
                'small' => '👥 Small Team (2-5)',
                'medium' => '🏢 Medium Team (6-20)',
                'large' => '🏭 Large Team (20+)',
            ],
            default: 'small'
        );

        $alertPreference = select(
            label: 'How quickly do you need to know about issues?',
            options: [
                'immediate' => '⚡ Immediately — Every critical error',
                'balanced' => '⚖️ Balanced — Grouped alerts every few minutes',
                'digest' => '📧 Daily Digest — Summary once per day',
            ],
            default: 'balanced'
        );

        // Apply configuration based on selections
        spin(
            message: 'Applying optimized configuration...',
            callback: function () use ($appType, $teamSize, $alertPreference) {
                $this->applyOptimizations($appType, $teamSize, $alertPreference);
                sleep(1);
            }
        );

        info('✅ Configuration applied!');
        info('Next steps:');
        info('  1. Review config/logpulse.php');
        info('  2. Run `php artisan logpulse:monitor` to see the live dashboard');
        info('  3. Run `php artisan logpulse:simulate` to test your alerts');

        return self::SUCCESS;
    }

    protected function applyOptimizations(string $appType, string $teamSize, string $alertPreference): void
    {
        $configPath = config_path('logpulse.php');
        
        if (!file_exists($configPath)) {
            $this->call('vendor:publish', ['--tag' => 'logpulse-config']);
        }

        $config = file_get_contents($configPath);

        // Apply app type optimizations
        $config = match($appType) {
            'api' => $this->optimizeForApi($config),
            'web' => $this->optimizeForWeb($config),
            'spa' => $this->optimizeForSpa($config),
            'microservice' => $this->optimizeForMicroservice($config),
            'ecommerce' => $this->optimizeForEcommerce($config),
        };

        // Apply team size optimizations
        $config = match($teamSize) {
            'solo' => str_replace(
                "'max_alerts_per_minute' => 5",
                "'max_alerts_per_minute' => 10",
                $config
            ),
            'medium' => str_replace(
                "'max_alerts_per_minute' => 5",
                "'max_alerts_per_minute' => 3",
                $config
            ),
            'large' => str_replace(
                "'max_alerts_per_minute' => 5",
                "'max_alerts_per_minute' => 2",
                $config
            ),
            default => $config,
        };

        file_put_contents($configPath, $config);
    }

    protected function optimizeForApi(string $config): string
    {
        return str_replace(
            "'preset' => 'saas-webhook'",
            "'preset' => 'api-gateway'",
            $config
        );
    }

    protected function optimizeForWeb(string $config): string
    {
        return str_replace(
            "'preset' => 'saas-webhook'",
            "'preset' => 'saas-webhook'",
            $config
        );
    }

    protected function optimizeForSpa(string $config): string
    {
        return str_replace(
            "'preset' => 'saas-webhook'",
            "'preset' => 'api-gateway'",
            $config
        );
    }

    protected function optimizeForMicroservice(string $config): string
    {
        return str_replace(
            "'preset' => 'saas-webhook'",
            "'preset' => 'api-gateway'",
            $config
        );
    }

    protected function optimizeForEcommerce(string $config): string
    {
        return str_replace(
            "'preset' => 'saas-webhook'",
            "'preset' => 'ecommerce'",
            $config
        );
    }
}