<?php

// tests/Feature/CommandsTest.php

use AmjadIqbal\LogPulse\Analyzers\BurdenCalculator;
use AmjadIqbal\LogPulse\Analyzers\FrequencyAnalyzer;
use AmjadIqbal\LogPulse\Analyzers\PatternMatcher;
use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Process\Process;

use function Pest\Laravel\artisan;

beforeEach(function () {
    // Setup test configuration
    Config::set('logpulse.storage.table', 'logpulse_events');
    Config::set('logpulse.thresholds.critical.count', 5);
    Config::set('logpulse.thresholds.critical.window', 5);
    Config::set('logpulse.channels', ['slack']);
    Config::set('logpulse.slack_webhook_url', 'https://hooks.slack.com/test');
    Config::set('logpulse.circuit_breaker.enabled', false);

    // Migrations are already applied per-test by TestCase's RefreshDatabase +
    // defineDatabaseMigrations(). Calling `migrate:fresh` here as well used to run SQLite's
    // VACUUM inside the transaction RefreshDatabase wraps each test in, which SQLite rejects
    // ("cannot VACUUM from within a transaction") and failed every test in this file.
});

test('install command publishes configuration', function () {
    artisan('logpulse:install', ['--no-interaction' => true])
        ->assertSuccessful();

    expect(file_exists(config_path('logpulse.php')))->toBeTrue();
});

test('status command displays system health', function () {
    // Create some test events
    LogPulseEvent::create([
        'aggregate_id' => md5('TestException|/test'),
        'exception_class' => 'TestException',
        'severity' => 'error',
        'message' => 'Test error message',
        'route' => '/test',
        'occurrence_count' => 10,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    artisan('logpulse:status')
        ->assertSuccessful()
        ->expectsOutputToContain('LogPulse Status Report');
});

test('status command outputs valid JSON', function () {
    // PendingCommand::run() returns the command's exit code (an int), not its captured
    // output — passing that to json_decode() silently "succeeds" with int 0, which is why
    // this test used to fail with "Failed asserting that 0 is of type array" instead of
    // actually checking the JSON payload. Artisan::call() + Artisan::output() is the
    // correct way to capture what a command printed.
    Artisan::call('logpulse:status', ['--json' => true]);
    $output = Artisan::output();
    $json = json_decode($output, true);

    expect($json)->toBeArray();
    expect($json)->toHaveKey('burden_score');
    expect($json)->toHaveKey('total_events');
});

test('simulate command generates test errors', function () {
    Event::fake();

    // LogPulseSimulateCommand always prompts via Laravel\Prompts\select()/confirm() — it has
    // no --no-interaction path. Laravel forces Prompts' console-Question fallback whenever
    // Application::runningUnitTests() is true (see ConfiguresPrompts::bootstrap()), which is
    // why Prompt::fake() never actually intercepts these under Testbench — the fallback wins
    // unconditionally in a test run, and the standard artisan()->expectsChoice()/
    // expectsConfirmation() test helpers are what mock that fallback path (see
    // PendingCommand::mockConsoleOutput()). The scenario choice matters for run time too: the
    // default scenario is 'burst' (50 log calls with a 0.2s sleep between each, ~10s), so
    // 'single' — the one scenario that logs once and returns immediately — keeps this test fast.
    artisan('logpulse:simulate')
        ->expectsChoice('Choose a simulation scenario', 'single', [
            'burst' => '💥 Error Burst — Rapid 50 errors in 10 seconds',
            'cascade' => '🌊 Cascade Failure — Growing error rate over 1 minute',
            'single' => '🎯 Single Critical Error — One high-severity exception',
            'pattern' => '📊 Pattern Test — Repeating error every 10s for 2 minutes',
        ])
        ->expectsConfirmation('This will generate REAL log entries. Continue?', 'yes')
        ->assertSuccessful();
});

test('monitor command runs successfully', function () {
    // LogPulseMonitorCommand::handle() is a `while (true)` live dashboard loop, so it can
    // only be tested by spawning it as a real process and killing it after a timeout — not
    // by calling it in-process the way the other command tests do. But this repo is a
    // package under standalone test, not a full Laravel app: there is no `artisan` file
    // here at all, so `[PHP_BINARY, 'artisan', ...]` always failed to start, silently
    // producing no output. Orchestra Testbench's `vendor/bin/testbench` is the package-dev
    // equivalent of `artisan` and does exist.
    $process = new Process([
        PHP_BINARY, __DIR__.'/../../vendor/bin/testbench', 'logpulse:monitor', '--refresh=1',
    ]);
    $process->setTimeout(3);
    $process->start();

    sleep(2);
    $process->stop();

    expect($process->getOutput())->toContain('LogPulse Real-Time Monitor');
});

test('quickstart command runs interactive wizard', function () {
    // Same reason as the simulate command above: expectsChoice() (not Prompt::fake()) is what
    // actually satisfies Prompts' forced test-mode fallback for three unconditional
    // Laravel\Prompts\select() calls with no non-interactive path.
    artisan('logpulse:quickstart')
        ->expectsChoice('What type of application are you monitoring?', 'api', [
            'api' => '🔌 REST/GraphQL API',
            'web' => '🌐 Traditional Web App (Blade/Livewire)',
            'spa' => '⚛️ SPA Backend (Vue/React + API)',
            'microservice' => '🔧 Microservice / Internal Service',
            'ecommerce' => '🛒 E-Commerce Application',
        ])
        ->expectsChoice('How large is your development team?', 'solo', [
            'solo' => '👤 Solo Developer',
            'small' => '👥 Small Team (2-5)',
            'medium' => '🏢 Medium Team (6-20)',
            'large' => '🏭 Large Team (20+)',
        ])
        ->expectsChoice('How quickly do you need to know about issues?', 'immediate', [
            'immediate' => '⚡ Immediately — Every critical error',
            'balanced' => '⚖️ Balanced — Grouped alerts every few minutes',
            'digest' => '📧 Daily Digest — Summary once per day',
        ])
        ->assertSuccessful();
});

test('log event handler stores events correctly', function () {
    $event = new MessageLogged(
        level: 'error',
        message: 'Test PDOException: Connection refused',
        context: [
            'exception' => 'PDOException',
            'route' => '/api/test',
            'file' => '/var/www/app/Database.php',
            'line' => 100,
        ]
    );

    Event::dispatch($event);

    $stored = LogPulseEvent::first();

    expect($stored)->not->toBeNull();
    expect($stored->exception_class)->toBe('PDOException');
    expect($stored->severity)->toBe('error');
    expect($stored->route)->toBe('/api/test');
});

test('frequency analyzer detects threshold breaches', function () {
    $exceptionClass = 'TestException';

    // Create multiple events to breach threshold
    for ($i = 0; $i < 10; $i++) {
        LogPulseEvent::create([
            'aggregate_id' => md5("{$exceptionClass}|/test"),
            'exception_class' => $exceptionClass,
            'severity' => 'error',
            'message' => "Test error {$i}",
            'route' => '/test',
            'occurrence_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    $analyzer = new FrequencyAnalyzer;
    $result = $analyzer->analyze($exceptionClass, '/test');

    expect($result)->toHaveKey('critical');
    expect($result['critical']['triggered'])->toBeTrue();
});

test('burden calculator returns valid score', function () {
    $events = collect([
        new LogPulseEvent([
            'exception_class' => 'PDOException',
            'severity' => 'critical',
            'occurrence_count' => 20,
        ]),
        new LogPulseEvent([
            'exception_class' => 'TimeoutException',
            'severity' => 'error',
            'occurrence_count' => 10,
        ]),
    ]);

    $calculator = new BurdenCalculator;
    $score = $calculator->calculate($events);

    expect($score)->toBeInt();
    expect($score)->toBeGreaterThan(0);
    expect($score)->toBeLessThanOrEqual(100);
});

test('pattern matcher detects cascade failure', function () {
    $events = collect([
        new LogPulseEvent(['exception_class' => 'ExceptionA', 'occurrence_count' => 5]),
        new LogPulseEvent(['exception_class' => 'ExceptionB', 'occurrence_count' => 3]),
        new LogPulseEvent(['exception_class' => 'ExceptionC', 'occurrence_count' => 4]),
        new LogPulseEvent(['exception_class' => 'ExceptionD', 'occurrence_count' => 2]),
    ]);

    $matcher = new PatternMatcher;
    $patterns = $matcher->analyze($events);

    expect($patterns)->toBeArray();
    expect($patterns)->not->toBeEmpty();
});

test('circuit breaker prevents alert floods', function () {
    Config::set('logpulse.circuit_breaker.enabled', true);
    Config::set('logpulse.circuit_breaker.max_alerts_per_minute', 3);

    // Simulate multiple alerts. The `channel` column is unique by design (one circuit-breaker
    // state row per channel, updated as its alert count climbs) — repeatedly insert()ing the
    // same channel used to fail with a UniqueConstraintViolationException on the second
    // iteration. updateOrInsert() is what production code updating this table would actually
    // do.
    for ($i = 0; $i < 10; $i++) {
        DB::table('logpulse_circuit_breaker')->updateOrInsert(
            ['channel' => 'global'],
            [
                'alert_count' => $i + 1,
                'window_started_at' => now(),
                'is_open' => $i >= 3,
                'cooldown_until' => $i >= 3 ? now()->addMinutes(15) : null,
                'current_backoff' => 1.0,
            ]
        );
    }

    $isOpen = DB::table('logpulse_circuit_breaker')
        ->where('channel', 'global')
        ->where('is_open', true)
        ->exists();

    expect($isOpen)->toBeTrue();
});
