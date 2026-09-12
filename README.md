<div align="center">

<img src="art/logo.png" alt="Laravel LogPulse" width="400">

# 🫀 Laravel LogPulse

**Proactive Log Health Monitoring & Intelligent Alerting for Laravel**

[![Latest Version on Packagist](https://img.shields.io/packagist/v/amjadiqbal/laravel-logpulse.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laravel-logpulse)
[![Total Downloads](https://img.shields.io/packagist/dt/amjadiqbal/laravel-logpulse.svg?style=flat-square)](https://packagist.org/packages/amjadiqbal/laravel-logpulse)
[![License](https://img.shields.io/packagist/l/amjadiqbal/laravel-logpulse.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/amjadiqbal/laravel-logpulse/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/amjadiqbal/laravel-logpulse/actions)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)

<p align="center">
  <a href="#-installation">Installation</a> •
  <a href="#-features">Features</a> •
  <a href="#-usage">Usage</a> •
  <a href="#-documentation">Docs</a> •
  <a href="#-sponsors">Sponsors</a>
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