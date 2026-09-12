{{-- resources/views/dashboard.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogPulse — System Health Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen" x-data="logPulseDashboard()" x-init="init()">
    
    {{-- Header --}}
    <header class="gradient-bg text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="text-3xl">🫀</span>
                    <div>
                        <h1 class="text-2xl font-bold">LogPulse</h1>
                        <p class="text-sm opacity-80">System Health Monitor</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm opacity-75" x-text="'Last updated: ' + lastUpdated"></span>
                    <button @click="refresh()" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        {{-- Status Bar --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            {{-- System Status --}}
            <div class="bg-white rounded-xl shadow-sm p-6 card-hover transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">System Status</p>
                        <p class="text-2xl font-bold mt-1" :class="statusColor" x-text="statusText"></p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="statusBgColor">
                        <span class="text-2xl pulse-dot" x-text="statusEmoji"></span>
                    </div>
                </div>
            </div>

            {{-- Burden Score --}}
            <div class="bg-white rounded-xl shadow-sm p-6 card-hover transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Burden Score</p>
                        <p class="text-2xl font-bold mt-1" x-text="burdenScore + '%'"></p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-orange-400 to-red-500">
                        <span class="text-2xl">🔥</span>
                    </div>
                </div>
                <div class="mt-3 bg-gray-200 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all duration-500" 
                         :style="'width: ' + burdenScore + '%'"
                         :class="burdenColor"></div>
                </div>
            </div>

            {{-- Events Today --}}
            <div class="bg-white rounded-xl shadow-sm p-6 card-hover transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Events Today</p>
                        <p class="text-2xl font-bold mt-1" x-text="eventsToday"></p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600">
                        <span class="text-2xl">📊</span>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-3" x-text="trendText"></p>
            </div>

            {{-- Circuit Breaker --}}
            <div class="bg-white rounded-xl shadow-sm p-6 card-hover transition-all duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Circuit Breaker</p>
                        <p class="text-2xl font-bold mt-1" x-text="circuitBreakerStatus"></p>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="circuitBreakerBg">
                        <span class="text-2xl">🛡️</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- Error Rate Chart --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Error Rate (Last Hour)</h3>
                <canvas id="errorRateChart" height="200"></canvas>
            </div>

            {{-- Severity Distribution --}}
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Severity Distribution</h3>
                <canvas id="severityChart" height="200"></canvas>
            </div>
        </div>

        {{-- Top Errors Table --}}
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4">Top Errors (Last 5 Minutes)</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-gray-500 border-b">
                            <th class="pb-3 font-medium">Exception</th>
                            <th class="pb-3 font-medium">Count</th>
                            <th class="pb-3 font-medium">Route</th>
                            <th class="pb-3 font-medium">Trend</th>
                            <th class="pb-3 font-medium">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="error in topErrors" :key="error.id">
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition">
                                <td class="py-3">
                                    <span class="mono text-sm font-medium" x-text="error.exception_class"></span>
                                </td>
                                <td class="py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="error.count > 50 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'"
                                          x-text="error.count"></span>
                                </td>
                                <td class="py-3 mono text-sm text-gray-600" x-text="error.route"></td>
                                <td class="py-3">
                                    <span x-html="error.trend_icon" class="text-sm"></span>
                                </td>
                                <td class="py-3 text-sm text-gray-500" x-text="error.last_seen_at"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Alerts --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Alerts</h3>
            <div class="space-y-3">
                <template x-for="alert in recentAlerts" :key="alert.id">
                    <div class="flex items-start space-x-3 p-3 rounded-lg" :class="alert.severity_bg">
                        <span class="text-xl" x-text="alert.severity_emoji"></span>
                        <div class="flex-1">
                            <p class="text-sm font-medium" x-text="alert.message"></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span x-text="alert.channel"></span> • 
                                <span x-text="alert.created_at"></span>
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </main>

    <script>
        function logPulseDashboard() {
            return {
                statusText: 'Loading...',
                statusColor: 'text-gray-600',
                statusBgColor: 'bg-gray-100',
                statusEmoji: '⏳',
                burdenScore: 0,
                burdenColor: 'bg-green-500',
                eventsToday: 0,
                trendText: '',
                circuitBreakerStatus: 'Normal',
                circuitBreakerBg: 'bg-gray-100',
                lastUpdated: '—',
                topErrors: [],
                recentAlerts: [],

                async init() {
                    await this.refresh();
                    this.startPolling();
                },

                async refresh() {
                    try {
                        const response = await fetch('{{ route("logpulse.api.status") }}');
                        const data = await response.json();
                        
                        this.updateStatus(data);
                        this.updateCharts(data);
                        this.lastUpdated = new Date().toLocaleTimeString();
                    } catch (error) {
                        console.error('Failed to fetch LogPulse data:', error);
                    }
                },

                updateStatus(data) {
                    // Status
                    if (data.burden_score >= 80) {
                        this.statusText = 'CRITICAL';
                        this.statusColor = 'text-red-600';
                        this.statusBgColor = 'bg-red-100';
                        this.statusEmoji = '🔴';
                    } else if (data.burden_score >= 50) {
                        this.statusText = 'WARNING';
                        this.statusColor = 'text-yellow-600';
                        this.statusBgColor = 'bg-yellow-100';
                        this.statusEmoji = '🟡';
                    } else {
                        this.statusText = 'HEALTHY';
                        this.statusColor = 'text-green-600';
                        this.statusBgColor = 'bg-green-100';
                        this.statusEmoji = '🟢';
                    }

                    // Burden Score
                    this.burdenScore = data.burden_score;
                    if (this.burdenScore >= 80) this.burdenColor = 'bg-red-500';
                    else if (this.burdenScore >= 50) this.burdenColor = 'bg-yellow-500';
                    else this.burdenColor = 'bg-green-500';

                    // Events
                    this.eventsToday = data.events_today;
                    this.trendText = data.trend_text;

                    // Circuit Breaker
                    this.circuitBreakerStatus = data.circuit_breaker_open ? 'OPEN' : 'CLOSED';
                    this.circuitBreakerBg = data.circuit_breaker_open ? 'bg-red-100' : 'bg-green-100';

                    // Top Errors
                    this.topErrors = data.top_errors;

                    // Recent Alerts
                    this.recentAlerts = data.recent_alerts;
                },

                updateCharts(data) {
                    this.renderErrorRateChart(data.error_rate_history);
                    this.renderSeverityChart(data.severity_distribution);
                },

                renderErrorRateChart(history) {
                    const ctx = document.getElementById('errorRateChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: history.labels,
                            datasets: [{
                                label: 'Errors',
                                data: history.values,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4,
                                fill: true,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true },
                            }
                        }
                    });
                },

                renderSeverityChart(distribution) {
                    const ctx = document.getElementById('severityChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Critical', 'Warning', 'Info', 'Debug'],
                            datasets: [{
                                data: [
                                    distribution.critical,
                                    distribution.warning,
                                    distribution.info,
                                    distribution.debug,
                                ],
                                backgroundColor: [
                                    '#EF4444',
                                    '#F59E0B',
                                    '#3B82F6',
                                    '#6B7280',
                                ],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                        }
                    });
                },

                startPolling() {
                    setInterval(() => this.refresh(), 10000);
                }
            }
        }
    </script>
</body>
</html>