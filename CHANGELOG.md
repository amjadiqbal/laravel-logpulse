# Changelog

All notable changes to `laravel-logpulse` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-09-19

Found and fixed during manual integration testing against a fresh Laravel 13 / PHP 8.5 install
(before publishing to Laravel News) — the first time this package was installed into a real app
alongside the other 5 packages in this batch, rather than tested in isolation via Testbench.

### Fixed
- **`composer require amjadiqbal/laravel-logpulse` failed on the current Laravel release.**
  `php` was capped at `^8.0|^8.1|^8.2|^8.3` (excluding 8.4/8.5) and `laravel/framework` at
  `^9.0|^10.0|^11.0` (excluding 12/13). Widened both to also accept `^8.4|^8.5` and `^12.0|^13.0`
  respectively — full Pest suite (11 tests) still passes unchanged.
- **`guzzlehttp/guzzle: "^7.0"` conflicted with a fresh Laravel 13 app's actual dependency
  resolution.** Laravel 13 allows `guzzlehttp/guzzle ^7.8.2 || ^8.0`, and a clean
  `composer create-project laravel/laravel` resolves it to `8.2.0`. This package's own code
  never instantiates Guzzle's `Client` directly — it only sends alerts via Laravel's `Http`
  facade (`AlertChannels/SlackChannel.php`, `DiscordChannel.php`, `WebhookChannel.php`) — so the
  `^7.0` ceiling was a stale, unnecessary direct constraint that blocked co-installing this
  package with a current Laravel app. Widened to `^7.0|^8.0`.
- **`laravel/prompts: "^0.1.15"` was narrower than what current Laravel actually ships.** A
  fresh Laravel 13 app locks `laravel/prompts` at `v0.3.24`. Widened to
  `^0.1.15|^0.2|^0.3` to match.

Manually verified after the fixes: `composer require` into a real Laravel 13 app (alongside all
5 other packages in this testing pass, confirming no cross-package conflicts), `logpulse-config`
and `logpulse-migrations` vendor:publish tags, `php artisan migrate`, `logpulse:status`,
`app('logpulse')`/facade resolution (the previously-fixed singleton), and a real `Log::error()`
call confirmed to land a row in `logpulse_events` via the `MessageLogged` event listener.

## [Unreleased]

First commit under version control (2026-09-12) — the code existed only on local disk before
this, with no git history and no working CI. This release is the "first time it's actually been
run" pass: every listed fix below was a real bug the test suite (once it could run at all) or
PHPStan (once Larastan was added) caught, not a style change.

### Added
- Real MIT `LICENSE.md` text (was present but empty despite `composer.json` declaring
  `"license": "MIT"`).
- `.gitignore`, so `vendor/`, `composer.lock`, and local artifacts never get committed.
- `phpunit.xml` (was missing entirely — `vendor/bin/pest` couldn't even start without it).
- `phpstan.neon.dist` plus `larastan/larastan` as a dev dependency, so PHPStan can understand
  Eloquent's magic static methods and model properties instead of reporting hundreds of false
  positives against `LogPulseEvent`.
- `.github/workflows/run-tests.yml` (existed but was 0 bytes — no CI had ever actually run).
- `.github/FUNDING.yml` (existed but was 0 bytes despite `composer.json` declaring GitHub
  Sponsors / Buy Me a Coffee funding links).
- `testbench.yaml` declaring the package's service provider, so `vendor/bin/testbench` (Orchestra
  Testbench's package-dev equivalent of `artisan`) can actually run this package's commands
  standalone — needed to test `logpulse:monitor`'s live-loop behavior as a real subprocess.

### Fixed
- **`LogPulseServiceProvider::register()`** constructed `new LogPulseManager($app)`, but
  `LogPulseManager::__construct()` takes zero arguments. Every resolution of the `logpulse`
  singleton — `app('logpulse')`, the `LogPulse` facade, anything type-hinting
  `LogPulseManager` — threw `ArgumentCountError`. Nothing in the test suite exercised this path,
  so it shipped broken.
- **`LogPulseServiceProvider::registerEventListeners()`** registered a listener against
  `Illuminate\Foundation\Events\ExceptionHandlerReported`, a class that does not exist anywhere
  in `laravel/framework`, pointing at `AmjadIqbal\LogPulse\Listeners\ExceptionReportedHandler`,
  which was never created. `::class` references don't need the class to exist at compile time, so
  this shipped silently; it would have thrown a fatal "class not found" the first time a real
  Laravel app's exception handler actually reported something. Removed rather than implemented —
  the working `MessageLogged` listener path is untouched.
- **`LogPulseInstallCommand`**: `--no-interaction` is a global Symfony Console option;
  redeclaring it in the command's own `$signature` threw `LogicException: An option named
  "no-interaction" already exists.` on every invocation. Also, `showWelcomeScreen()` called
  `pause('Press ENTER...')` unconditionally, before the `--no-interaction` check further down
  even ran — defeating the flag's entire purpose for CI/scripted installs.
- **`NotationManager`-adjacent bug, actually `PatternMatcher::detectCascadeFailure()`**: counted
  the events *collection* size instead of summing `occurrence_count` — the field every other
  analyzer in this package treats as the real error volume. Pre-aggregated rows (the normal
  shape of data in this table) could never trip the cascade-failure threshold no matter how much
  real traffic they represented.
- **`FrequencyAnalyzer::getTopExceptions()`** called `->diffForHumans()` directly on
  `$event->last_seen`, a raw `MAX(last_seen_at)` SQL aggregate aliased to a different attribute
  name — a plain string, not the Carbon instance the model's `$casts` give a real `last_seen_at`
  column. Fatal error on every call.
- **`LogPulseQuickstartCommand`/`LogPulseSimulateCommand`/`LogPulseInstallCommand`**: three
  `match` expressions over `select()`'s return value had no `default` arm; added one to each
  that throws on an unexpected value, rather than trust only the declared option keys ever
  arrive.
- **`LogPulseMonitorCommand::progressBar()`** passed a `float` (from `round()`) to `str_repeat()`,
  which expects `int`.
- **`PatternMatcher::detectRetryStorm()`** passed `float` (from `floor()`) to `array_slice()`,
  which expects `int`.
- **`WebhookChannel`** used the nullsafe operator (`?->`) on `first_seen_at`/`last_seen_at`,
  which are non-nullable `timestamp` columns per the migration — switched to `->`.
- Test suite (`tests/Feature/CommandsTest.php`), all discovered by actually running it for the
  first time:
  - `beforeEach()` called `artisan('migrate:fresh')` on top of `RefreshDatabase` (already applied
    per-test via `TestCase::defineDatabaseMigrations()`) — `migrate:fresh` runs SQLite's `VACUUM`,
    which SQLite refuses inside the transaction `RefreshDatabase` wraps every test in. Removed
    the redundant call.
  - "status command outputs valid JSON" passed `PendingCommand::run()`'s return value (the
    command's *exit code*, an int) to `json_decode()`, which "succeeds" with int `0` — the
    JSON payload was never actually checked. Switched to `Artisan::call()` + `Artisan::output()`.
  - "simulate"/"quickstart" command tests didn't stub the interactive `Laravel\Prompts\select()`/
    `confirm()` prompts those commands always ask (no `--no-interaction` path exists). Laravel
    forces Prompts' console-fallback whenever running under Testbench, which needs
    `->expectsChoice()`/`->expectsConfirmation()` (not `Prompt::fake()`, which never actually
    intercepts under that forced fallback) — added.
  - "monitor command runs successfully" spawned `[PHP_BINARY, 'artisan', ...]`, but this repo is
    a package under standalone test with no `artisan` file at all — the process always failed to
    start, silently producing no output the assertion could ever match. Switched to
    `vendor/bin/testbench` (see `testbench.yaml` above).
  - "circuit breaker prevents alert floods" repeatedly `insert()`ed the same `channel = 'global'`
    value into a column with a `unique()` constraint, failing with
    `UniqueConstraintViolationException` on the second iteration. Switched to
    `updateOrInsert()` — what production code updating this table would actually do.
  - "pattern matcher detects cascade failure" — no test change needed once the underlying
    `detectCascadeFailure()` bug above was fixed; the test's data (4 aggregated rows, 14 total
    occurrences) was correct all along.

### Known limitations
- `require.php` claims `^8.0|^8.1|^8.2|^8.3`, but the pinned dev/test tooling
  (`pestphp/pest ^2.0`, `orchestra/testbench ^9.0`, `larastan ^2.0|^3.0`) needs PHP >=8.2 to
  install at all. CI only tests 8.2/8.3 as a result — the 8.0/8.1 claim in `require.php` is
  unverified by anything in this repo.
- No version tag yet; not submitted to Packagist.
