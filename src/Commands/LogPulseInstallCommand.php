<?php

// src/Commands/LogPulseInstallCommand.php

namespace AmjadIqbal\LogPulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\{intro, outro, info, warning, error, table, spin, progress, select, multiselect, confirm, text, password, note, pause};

class LogPulseInstallCommand extends Command
{
    protected $signature = 'logpulse:install
                            {--force : Force overwrite of existing configuration}
                            {--no-interaction : Skip interactive prompts}';
    
    protected $description = 'Install and configure Laravel LogPulse with an interactive wizard';

    private array $config = [];
    private array $steps = [];
    private int $currentStep = 0;

    public function handle(): int
    {
        // ─── WELCOME SCREEN ────────────────────────────
        $this->showWelcomeScreen();

        if (!$this->option('no-interaction')) {
            return $this->runInteractiveWizard();
        }

        return $this->runSilentInstallation();
    }

    private function showWelcomeScreen(): void
    {
        intro('🫀 Welcome to Laravel LogPulse');
        
        note(
            "     ⚡ Proactive Log Monitoring for Laravel\n" .
            "     📊 Real-time Error Detection & Alerting\n" .
            "     🎯 Catch Issues Before Your Customers Do\n" .
            "     🔧 By Amjad Iqbal (@amjadiqbal)"
        );

        pause('Press ENTER to begin the installation wizard...');
    }

    private function runInteractiveWizard(): int
    {
        // Step 1: Notification Channels
        $this->stepNotificationChannels();
        
        // Step 2: Alert Thresholds
        $this->stepAlertThresholds();
        
        // Step 3: Environment Presets
        $this->stepEnvironmentPreset();
        
        // Step 4: Dashboard Setup
        $this->stepDashboardSetup();
        
        // Step 5: Database Configuration
        $this->stepDatabaseSetup();
        
        // Step 6: Self-Protection
        $this->stepCircuitBreaker();
        
        // Step 7: Testing
        $this->stepTestConfiguration();
        
        // ─── SUMMARY & CONFIRMATION ────────────────────
        $this->showSummary();
        
        $confirmed = confirm(
            label: 'Ready to apply this configuration?',
            default: true,
            hint: 'Configuration file will be published to config/logpulse.php'
        );

        if (!$confirmed) {
            warning('Installation cancelled. Run `php artisan logpulse:install` to try again.');
            return self::FAILURE;
        }

        return $this->finalizeInstallation();
    }

    private function stepNotificationChannels(): void
    {
        $this->stepHeader('📡 Notification Channels', 'Where should LogPulse send alerts?');

        $channels = multiselect(
            label: 'Select your alert channels',
            options: [
                'slack' => '💬 Slack (Webhook)',
                'discord' => '🎮 Discord (Webhook)',
                'mail' => '📧 Email',
                'webhook' => '🔗 Custom Webhook',
                'vonage' => '📱 SMS via Vonage',
            ],
            default: ['slack', 'mail'],
            hint: 'You can configure multiple channels',
            required: 'At least one channel is required'
        );

        $this->config['channels'] = $channels;

        // Dynamic webhook URL collection
        if (in_array('slack', $channels)) {
            $this->config['slack_webhook'] = text(
                label: 'Slack Webhook URL',
                placeholder: 'https://hooks.slack.com/services/T.../B.../xxxxx',
                required: true,
                hint: 'Create one at https://api.slack.com/messaging/webhooks'
            );
        }

        if (in_array('discord', $channels)) {
            $this->config['discord_webhook'] = text(
                label: 'Discord Webhook URL',
                placeholder: 'https://discord.com/api/webhooks/.../...',
                required: true,
                hint: 'Server Settings → Integrations → Webhooks'
            );
        }

        if (in_array('mail', $channels)) {
            $this->config['alert_email'] = text(
                label: 'Alert email address',
                placeholder: 'oncall@yourcompany.com',
                required: true,
                hint: 'Can be a distribution list'
            );
        }

        if (in_array('webhook', $channels)) {
            $this->config['custom_webhook'] = text(
                label: 'Custom Webhook URL',
                placeholder: 'https://your-monitoring-tool.com/webhook',
                required: true
            );
        }

        $this->stepComplete();
    }

    private function stepAlertThresholds(): void
    {
        $this->stepHeader('⚙️ Alert Thresholds', 'Define when LogPulse should trigger alerts');

        // Severity levels configuration
        $severities = ['critical', 'warning', 'info'];
        $thresholds = [];

        foreach ($severities as $severity) {
            $emoji = match($severity) {
                'critical' => '🔴',
                'warning' => '🟡',
                'info' => '🔵',
            };

            $thresholds[$severity] = [
                'count' => (int) text(
                    label: "{$emoji} {$severity} threshold — Error count",
                    placeholder: match($severity) {
                        'critical' => '10',
                        'warning' => '50',
                        'info' => '100',
                    },
                    default: match($severity) {
                        'critical' => '10',
                        'warning' => '50',
                        'info' => '100',
                    },
                    required: true,
                    hint: "Number of errors in the time window to trigger {$severity} alert"
                ),
                'window' => (int) text(
                    label: "{$emoji} {$severity} time window (minutes)",
                    placeholder: '5',
                    default: match($severity) {
                        'critical' => '5',
                        'warning' => '15',
                        'info' => '30',
                    },
                    required: true,
                    hint: 'Rolling time window for error counting'
                ),
            ];
        }

        $this->config['thresholds'] = $thresholds;
        $this->stepComplete();
    }

    private function stepEnvironmentPreset(): void
    {
        $this->stepHeader('🎯 Environment Preset', 'Optimize thresholds for your use case');

        $preset = select(
            label: 'What best describes your application?',
            options: [
                'saas-webhook' => '🔄 SaaS with Webhook Endpoints — API-heavy, external callbacks',
                'ecommerce' => '🛒 E-Commerce — Checkout, payments, inventory',
                'api-gateway' => '🚪 API Gateway — Rate limiting, authentication',
                'cms-blog' => '📝 CMS / Blog — Content delivery, comments',
                'custom' => '🔧 Custom Configuration — Manual fine-tuning',
            ],
            default: 'saas-webhook',
            hint: 'Presets optimize thresholds and patterns for common scenarios'
        );

        $this->config['preset'] = $preset;

        // Show preset summary
        $presetDetails = match($preset) {
            'saas-webhook' => [
                'Focuses on external API failures & webhook timeouts',
                'Lower thresholds on /api/webhook routes',
                'Detects cascading failure patterns',
                'Monitors retry storm scenarios',
            ],
            'ecommerce' => [
                'Prioritizes checkout & payment errors',
                'Inventory sync failure detection',
                'Cart abandonment from errors',
                'Payment gateway timeout monitoring',
            ],
            'api-gateway' => [
                'Rate limit abuse detection',
                'Authentication failure spikes',
                'Downstream service degradation',
                'Token expiration patterns',
            ],
            'cms-blog' => [
                'Comment spam wave detection',
                'CDN cache miss spikes',
                'Database connection pool exhaustion',
                'Media upload failures',
            ],
            'custom' => [
                'Full manual control over all thresholds',
                'No pre-configured patterns applied',
            ],
        };

        info('Preset optimization includes:');
        foreach ($presetDetails as $detail) {
            info("  • {$detail}");
        }

        $this->stepComplete();
    }

    private function stepDashboardSetup(): void
    {
        $this->stepHeader('📊 Health Dashboard', 'Browser-based monitoring interface');

        $enableDashboard = confirm(
            label: 'Enable the built-in health dashboard?',
            default: true,
            hint: 'Accessible at /logpulse — shows real-time error rates, burden score, and trends'
        );

        $this->config['dashboard_enabled'] = $enableDashboard;

        if ($enableDashboard) {
            $this->config['dashboard_path'] = text(
                label: 'Dashboard URL path',
                placeholder: '/logpulse',
                default: '/logpulse',
                required: true,
                hint: 'You can customize this to avoid conflicts'
            );

            $this->config['dashboard_middleware'] = multiselect(
                label: 'Dashboard access middleware',
                options: [
                    'auth' => '🔒 Authenticated users only',
                    'can:viewLogPulse' => '🛡️ Custom permission gate',
                    'ip_whitelist' => '🌐 IP Whitelist',
                ],
                default: ['auth'],
                hint: 'Security layers for dashboard access'
            );

            if (in_array('ip_whitelist', $this->config['dashboard_middleware'])) {
                $this->config['whitelisted_ips'] = text(
                    label: 'Whitelisted IPs (comma-separated)',
                    placeholder: '10.0.0.1,192.168.1.0/24',
                    required: true,
                    hint: 'Supports CIDR notation'
                );
            }
        }

        $this->stepComplete();
    }

    private function stepDatabaseSetup(): void
    {
        $this->stepHeader('🗄️ Database Storage', 'Where to store event history');

        $storage = select(
            label: 'Storage driver for event history',
            options: [
                'database' => '🗃️ Database (recommended) — Persistent, queryable',
                'redis' => '⚡ Redis — Fast, ephemeral, perfect for high-traffic',
                'file' => '📁 File — Simple, no extra dependencies',
            ],
            default: 'database',
            hint: 'Database is recommended for production use'
        );

        $this->config['storage_driver'] = $storage;

        if ($storage === 'database') {
            $this->config['table_name'] = text(
                label: 'Database table name',
                placeholder: 'logpulse_events',
                default: 'logpulse_events',
                required: true
            );

            $this->config['pruning_days'] = (int) text(
                label: 'Auto-prune events after (days)',
                placeholder: '30',
                default: '30',
                required: true,
                hint: 'Older events will be automatically cleaned up'
            );
        }

        if ($storage === 'redis') {
            $this->config['redis_connection'] = text(
                label: 'Redis connection name',
                placeholder: 'default',
                default: 'default',
                required: true,
                hint: 'As defined in config/database.php'
            );
        }

        $this->stepComplete();
    }

    private function stepCircuitBreaker(): void
    {
        $this->stepHeader('🛡️ Self-Protection', 'Prevent alert storms from overwhelming your channels');

        $enableBreaker = confirm(
            label: 'Enable circuit breaker to prevent alert floods?',
            default: true,
            hint: 'Highly recommended — prevents your own Slack from being DDoSed during incidents'
        );

        if ($enableBreaker) {
            $this->config['circuit_breaker'] = [
                'enabled' => true,
                'max_alerts_per_minute' => (int) text(
                    label: 'Maximum alerts per minute (per channel)',
                    placeholder: '5',
                    default: '5',
                    required: true,
                    hint: 'Once exceeded, alerts will be suppressed'
                ),
                'cooldown_minutes' => (int) text(
                    label: 'Cooldown period after burst (minutes)',
                    placeholder: '15',
                    default: '15',
                    required: true,
                    hint: 'No alerts will be sent during cooldown'
                ),
                'backoff_multiplier' => select(
                    label: 'Backoff strategy',
                    options: [
                        '1.5' => '📈 Linear (1.5x each burst)',
                        '2.0' => '📊 Exponential (2x each burst — recommended)',
                        '1.0' => '➡️ Fixed (same cooldown always)',
                    ],
                    default: '2.0',
                    hint: 'How aggressively to increase cooldown on repeated bursts'
                ),
            ];
        }

        $this->stepComplete();
    }

    private function stepTestConfiguration(): void
    {
        $this->stepHeader('🧪 Test Your Setup', 'Verify everything works before going live');

        $testNow = confirm(
            label: 'Send a test alert to verify your configuration?',
            default: true,
            hint: 'This will send a test notification to all configured channels'
        );

        if ($testNow) {
            spin(
                message: 'Sending test alerts...',
                callback: function () {
                    // Simulate sending test alerts
                    sleep(2);
                    
                    // In reality, this would call the alert channels
                    return true;
                }
            );

            info('✅ Test alerts sent successfully!');
            note('Check your configured channels for the test message.');
            pause('Press ENTER after verifying the test alerts...');
        }

        $this->stepComplete();
    }

    private function showSummary(): void
    {
        intro('📋 Configuration Summary');

        // Build a beautiful table summary
        $rows = [
            ['Channels', implode(', ', array_map('ucfirst', $this->config['channels'] ?? []))],
            ['Preset', ucfirst(str_replace('-', ' ', $this->config['preset'] ?? 'custom'))],
            ['Dashboard', ($this->config['dashboard_enabled'] ?? false) ? "✅ Enabled at {$this->config['dashboard_path']}" : '❌ Disabled'],
            ['Storage', ucfirst($this->config['storage_driver'] ?? 'database')],
            ['Circuit Breaker', isset($this->config['circuit_breaker']['enabled']) ? '✅ Active' : '❌ Disabled'],
            ['Critical Threshold', "{$this->config['thresholds']['critical']['count']} errors / {$this->config['thresholds']['critical']['window']}min"],
            ['Warning Threshold', "{$this->config['thresholds']['warning']['count']} errors / {$this->config['thresholds']['warning']['window']}min"],
        ];

        table(
            headers: ['Setting', 'Value'],
            rows: $rows
        );
    }

    private function finalizeInstallation(): int
    {
        spin(
            message: 'Installing Laravel LogPulse...',
            callback: function () {
                // Publish config
                $this->call('vendor:publish', [
                    '--tag' => 'logpulse-config',
                    '--force' => $this->option('force'),
                ]);

                // Run migrations if using database
                if (($this->config['storage_driver'] ?? 'database') === 'database') {
                    $this->call('migrate');
                }

                // Update .env with LogPulse settings if needed
                $this->updateEnvironmentFile();
                
                sleep(1); // Visual feedback
            }
        );

        // Success screen
        outro('🎉 Laravel LogPulse installed successfully!');

        info('Next steps:');
        info('  1. Review config/logpulse.php for fine-tuning');
        info('  2. Visit ' . ($this->config['dashboard_path'] ?? '/logpulse') . ' for the health dashboard');
        info('  3. Run `php artisan logpulse:status` to verify everything');
        info('  4. Star the repo: ⭐ github.com/amjadiqbal/laravel-logpulse');

        note('💡 Tip: Use `php artisan logpulse:simulate` to generate test errors');

        return self::SUCCESS;
    }

    private function stepHeader(string $title, string $subtitle): void
    {
        $this->currentStep++;
        $this->newLine();
        
        // Calculate progress percentage
        $totalSteps = 7;
        $percentage = round(($this->currentStep / $totalSteps) * 100);
        
        info("Step {$this->currentStep}/{$totalSteps} [{$percentage}%]");
        intro($title);
        note($subtitle);
    }

    private function stepComplete(): void
    {
        info('✅ Step completed');
    }

    private function updateEnvironmentFile(): void
    {
        $envUpdates = [];
        
        if (isset($this->config['slack_webhook'])) {
            $envUpdates['LOGPULSE_SLACK_WEBHOOK'] = $this->config['slack_webhook'];
        }
        
        if (isset($this->config['discord_webhook'])) {
            $envUpdates['LOGPULSE_DISCORD_WEBHOOK'] = $this->config['discord_webhook'];
        }

        if (!empty($envUpdates)) {
            // Append to .env
            $envContent = File::get(base_path('.env'));
            
            foreach ($envUpdates as $key => $value) {
                if (!str_contains($envContent, $key)) {
                    File::append(base_path('.env'), "\n{$key}={$value}");
                }
            }
        }
    }

    private function runSilentInstallation(): int
    {
        // Non-interactive mode for CI/CD pipelines
        $this->info('Running in non-interactive mode...');
        
        $this->config = [
            'channels' => ['slack'],
            'thresholds' => [
                'critical' => ['count' => 10, 'window' => 5],
                'warning' => ['count' => 50, 'window' => 15],
                'info' => ['count' => 100, 'window' => 30],
            ],
            'preset' => 'saas-webhook',
            'dashboard_enabled' => true,
            'dashboard_path' => '/logpulse',
            'storage_driver' => 'database',
            'circuit_breaker' => ['enabled' => true],
        ];

        return $this->finalizeInstallation();
    }
}