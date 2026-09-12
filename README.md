<div align="center">

<img src="art/logo.png" alt="Laravel LogPulse" width="400">

# 🫀 Laravel LogPulse

**Proactive Log Health Monitoring & Intelligent Alerting for Laravel**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amjadiqbal/laravel-logpulse.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laravel-logpulse)
[![Total Downloads](https://img.shields.io/packagist/dt/amjadiqbal/laravel-logpulse.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laravel-logpulse)
[![License](https://img.shields.io/packagist/l/amjadiqbal/laravel-logpulse.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/AmjadIqbal/laravel-logpulse/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/AmjadIqbal/laravel-logpulse/actions)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen.svg?style=flat-square)](https://phpstan.org/)

<p align="center">
  <a href="#-installation">Installation</a> •
  <a href="#️-configuration">Configuration</a> •
  <a href="#️-usage">Usage</a> •
  <a href="#-how-alerting-works">How it works</a>
</p>

</div>

---

## 🤔 The Problem

Your production app is silently failing. A webhook endpoint returns 500 errors 200 times per hour. Your disk fills with logs. Your database connections exhaust. And you don't know until a customer complains.

**Traditional log viewers are reactive — you only see problems when you manually check.**

---

## ✨ The Solution

LogPulse acts as a heartbeat monitor for your Laravel application. It listens to your logging engine in real-time, detects abnormal error patterns, and **alerts you before your customers notice.**

<div align="center">
  <img src="art/dashboard-screenshot.png" alt="LogPulse Dashboard" width="800">
</div>

---

## 🚀 Features

- ⚡ **Real-time error detection** — Catches error spikes as they happen
- 🎯 **Intelligent threshold alerts** — Not just count, but pattern-based detection
- 📊 **System Burden Score** — Understand the impact, not just the count
- 🔔 **Multi-channel notifications** — Slack, Discord, Email, SMS, Webhooks
- 🛡️ **Self-protecting** — Circuit breaker prevents alert storms
- 📈 **Adaptive baselines** — Learns your normal patterns (Pro)
- 🎨 **Beautiful dashboard** — Built-in health monitoring UI
- 🧪 **Simulation mode** — Test your alerts without waiting for real errors
- 🎮 **Interactive CLI** — Laravel Prompts-powered installation wizard
- 🔌 **Ecosystem integration** — Works with Pulse, Horizon, Telescope

---

## 📦 Installation

```bash
composer require amjadiqbal/laravel-logpulse
```

Guided setup (recommended):

```bash
php artisan logpulse:install
```

Or non-interactively (CI/scripted installs):

```bash
php artisan logpulse:install --no-interaction
```

Publish the config manually instead, if you'd rather configure by hand:

```bash
php artisan vendor:publish --tag=logpulse-config
```

## ⚙️ Configuration

Everything lives in `config/logpulse.php`, driven by environment variables:

```env
LOGPULSE_CHANNELS=slack,mail
LOGPULSE_SLACK_WEBHOOK=https://hooks.slack.com/services/...
LOGPULSE_DISCORD_WEBHOOK=https://discord.com/api/webhooks/...
LOGPULSE_ALERT_EMAIL=oncall@yourcompany.com

LOGPULSE_CRITICAL_COUNT=10
LOGPULSE_CRITICAL_WINDOW=5
LOGPULSE_WARNING_COUNT=50
LOGPULSE_WARNING_WINDOW=15

LOGPULSE_PRESET=saas-webhook
LOGPULSE_DASHBOARD=true
LOGPULSE_DASHBOARD_PATH=/logpulse
```

- **Channels**: `slack`, `discord`, `mail`, `webhook`, `vonage` (SMS) — any combination.
- **Thresholds**: error count within a rolling time window, per severity (`critical`/`warning`/`info`).
- **Presets**: `saas-webhook`, `ecommerce`, `api-gateway`, `cms-blog`, or `custom` — pre-tuned
  thresholds and pattern detection for common application shapes. `logpulse:quickstart` walks
  you through picking one.
- **Dashboard**: a built-in `/logpulse` route (behind `web`+`auth` middleware by default) showing
  live burden score, top errors, and circuit-breaker status.

## 🖥️ Usage

```bash
# Live terminal dashboard — burden score, top errors, alert status, refreshing every N seconds
php artisan logpulse:monitor --refresh=3

# Current status as a table, or as JSON for scripting/health checks
php artisan logpulse:status
php artisan logpulse:status --json

# Generate test errors to verify your alert channels actually fire
php artisan logpulse:simulate
```

LogPulse listens to Laravel's own `MessageLogged` event automatically once installed — no extra
wiring needed for `Log::error()`/`Log::critical()` calls to start feeding the dashboard and
alert pipeline.

## 🛡️ How alerting works

1. Every logged error is aggregated by `(exception_class, route)` into a `logpulse_events` row,
   with an `occurrence_count` rather than one row per occurrence.
2. `FrequencyAnalyzer` checks the rolling count against your configured thresholds.
3. `PatternMatcher` looks for higher-level patterns across recent events — cascade failures
   (many different exceptions in a burst), retry storms (exponentially shortening intervals),
   dependency outages, resource exhaustion, and degraded performance.
4. A triggered alert goes out over your configured channels — unless the circuit breaker for
   that channel is open, which caps how many alerts can fire per minute so a real incident
   doesn't also turn into an alert flood.

## 📄 License

MIT License. See [LICENSE.md](LICENSE.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Author

**Amjad Iqbal** — [amjad.com.pk](https://amjad.com.pk) · [hi@amjad.com.pk](mailto:hi@amjad.com.pk) · [GitHub](https://github.com/AmjadIqbal)