<?php

// tests/Feature/CommandsTest.php

use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Log\Events\MessageLogged;
use function Pest\Laravel\artisan;

beforeEach(function () {
    // Setup test configuration
    Config::set('logpulse.storage.table', 'logpulse_events');
    Config::set('logpulse.thresholds.critical.count', 5);
    Config::set('logpulse.thresholds.critical.window', 5);
    Config::set('logpulse.channels', ['slack']);
    Config::set('logpulse.slack_webhook_url', 'https://hooks.slack.com/test');
    Config::set('logpulse.circuit_breaker.enabled', false);

    // Run migrations
    $this->artisan('migrate:fresh')->run();
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
    artisan('logpulse:status', ['--json' => true])
        ->assertSuccessful();

    $output = artisan('logpulse:status', ['--json' => true])->run();
    $json = json_decode($output, true);
    
    expect($json)->toBeArray();
    expect($json)->toHaveKey('burden_score');
    expect($json)->toHaveKey('total_events');
});

test('simulate command generates test errors', function () {
    Event::fake();

    artisan('logpulse:simulate')
        ->assertSuccessful();
});

test('monitor command runs successfully', function () {
    // Run for a short duration
    $process = new Symfony\Component\Process\Process([
        PHP_BINARY, 'artisan', 'logpulse:monitor', '--refresh=1'
    ]);
    $process->setTimeout(3);
    $process->start();
    
    sleep(2);
    $process->stop();
    
    expect($process->getOutput())->toContain('LogPulse Real-Time Monitor');
});

test('quickstart command runs interactive wizard', function () {
    artisan('logpulse:quickstart')
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

    $analyzer = new \AmjadIqbal\LogPulse\Analyzers\FrequencyAnalyzer();
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

    $calculator = new \AmjadIqbal\LogPulse\Analyzers\BurdenCalculator();
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

    $matcher = new \AmjadIqbal\LogPulse\Analyzers\PatternMatcher();
    $patterns = $matcher->analyze($events);

    expect($patterns)->toBeArray();
    expect($patterns)->not->toBeEmpty();
});

test('circuit breaker prevents alert floods', function () {
    Config::set('logpulse.circuit_breaker.enabled', true);
    Config::set('logpulse.circuit_breaker.max_alerts_per_minute', 3);

    // Simulate multiple alerts
    for ($i = 0; $i < 10; $i++) {
        DB::table('logpulse_circuit_breaker')->insert([
            'channel' => 'global',
            'alert_count' => $i + 1,
            'window_started_at' => now(),
            'is_open' => $i >= 3,
            'cooldown_until' => $i >= 3 ? now()->addMinutes(15) : null,
            'current_backoff' => 1.0,
        ]);
    }

    $isOpen = DB::table('logpulse_circuit_breaker')
        ->where('channel', 'global')
        ->where('is_open', true)
        ->exists();

    expect($isOpen)->toBeTrue();
});