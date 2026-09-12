<?php

// src/Dashboard/Http/Controllers/LogPulseDashboardController.php

namespace AmjadIqbal\LogPulse\Dashboard\Http\Controllers;

use AmjadIqbal\LogPulse\Analyzers\BurdenCalculator;
use AmjadIqbal\LogPulse\Analyzers\FrequencyAnalyzer;
use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class LogPulseDashboardController extends Controller
{
    /**
     * Display the main dashboard.
     */
    public function index()
    {
        return view('logpulse::dashboard');
    }

    /**
     * Get current status for real-time updates.
     */
    public function status(): JsonResponse
    {
        $events = LogPulseEvent::where('last_seen_at', '>=', now()->subMinutes(30))->get();
        
        $burdenCalculator = new BurdenCalculator();
        $frequencyAnalyzer = new FrequencyAnalyzer();
        
        $burdenScore = $burdenCalculator->calculate($events);
        $interpretation = $burdenCalculator->getInterpretation($burdenScore);

        // Error rate history (last hour, in 5-minute intervals)
        $errorRateHistory = $this->getErrorRateHistory();
        
        // Severity distribution
        $severityDistribution = $this->getSeverityDistribution();

        return response()->json([
            'burden_score' => $burdenScore,
            'interpretation' => $interpretation,
            'events_today' => LogPulseEvent::whereDate('created_at', today())->count(),
            'trend_text' => $this->getTrendText(),
            'circuit_breaker_open' => $this->isCircuitBreakerOpen(),
            'top_errors' => $frequencyAnalyzer->getTopExceptions(10, 5),
            'recent_alerts' => $this->getRecentAlerts(),
            'error_rate_history' => $errorRateHistory,
            'severity_distribution' => $severityDistribution,
        ]);
    }

    /**
     * Get paginated events list.
     */
    public function events(Request $request): JsonResponse
    {
        $events = LogPulseEvent::query()
            ->when($request->severity, fn($q) => $q->ofSeverity($request->severity))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('exception_class', 'like', "%{$request->search}%")
                        ->orWhere('message', 'like', "%{$request->search}%")
                        ->orWhere('route', 'like', "%{$request->search}%");
                });
            })
            ->orderByDesc('last_seen_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($events);
    }

    /**
     * Show a single event.
     */
    public function show(int $id): JsonResponse
    {
        $event = LogPulseEvent::findOrFail($id);
        return response()->json($event);
    }

    /**
     * Get error rate chart data.
     */
    public function errorRateChart(): JsonResponse
    {
        return response()->json($this->getErrorRateHistory());
    }

    /**
     * Get severity distribution chart data.
     */
    public function severityChart(): JsonResponse
    {
        return response()->json($this->getSeverityDistribution());
    }

    /**
     * Reset circuit breaker.
     */
    public function resetCircuitBreaker(): JsonResponse
    {
        DB::table('logpulse_circuit_breaker')
            ->update([
                'is_open' => false,
                'alert_count' => 0,
                'cooldown_until' => null,
                'current_backoff' => 1.0,
            ]);

        return response()->json(['message' => 'Circuit breaker reset successfully']);
    }

    /**
     * Purge old events.
     */
    public function purgeEvents(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        
        $deleted = LogPulseEvent::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Purged {$deleted} events older than {$days} days",
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Get error rate history for the last hour in 5-minute intervals.
     */
    protected function getErrorRateHistory(): array
    {
        $labels = [];
        $values = [];
        
        for ($i = 60; $i >= 0; $i -= 5) {
            $start = now()->subMinutes($i);
            $end = now()->subMinutes(max($i - 5, 0));
            
            $count = LogPulseEvent::whereBetween('last_seen_at', [$start, $end])
                ->sum('occurrence_count');
            
            $labels[] = $start->format('H:i');
            $values[] = $count;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get severity distribution for today.
     */
    protected function getSeverityDistribution(): array
    {
        $today = LogPulseEvent::whereDate('created_at', today());
        
        return [
            'critical' => (clone $today)->where('severity', 'critical')->count(),
            'warning' => (clone $today)->where('severity', 'warning')->count(),
            'info' => (clone $today)->where('severity', 'info')->count(),
            'debug' => (clone $today)->where('severity', 'debug')->count(),
        ];
    }

    /**
     * Get trend text comparing today vs yesterday.
     */
    protected function getTrendText(): string
    {
        $today = LogPulseEvent::whereDate('created_at', today())->count();
        $yesterday = LogPulseEvent::whereDate('created_at', today()->subDay())->count();

        if ($yesterday === 0) {
            return 'No data from yesterday to compare';
        }

        $change = (($today - $yesterday) / $yesterday) * 100;
        
        if ($change > 20) {
            return "↑ {$change}% vs yesterday";
        } elseif ($change < -20) {
            return "↓ " . abs($change) . "% vs yesterday";
        }
        
        return '→ Similar to yesterday';
    }

    /**
     * Check if circuit breaker is open.
     */
    protected function isCircuitBreakerOpen(): bool
    {
        return DB::table('logpulse_circuit_breaker')
            ->where('channel', 'global')
            ->where('is_open', true)
            ->exists();
    }

    /**
     * Get recent alerts from the last 24 hours.
     */
    protected function getRecentAlerts(): array
    {
        return DB::table('logpulse_alerts')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($alert) {
                $severityEmoji = match($alert->severity) {
                    'critical' => '🚨',
                    'warning' => '⚠️',
                    'info' => 'ℹ️',
                    default => '📊',
                };

                $severityBg = match($alert->severity) {
                    'critical' => 'bg-red-50',
                    'warning' => 'bg-yellow-50',
                    'info' => 'bg-blue-50',
                    default => 'bg-gray-50',
                };

                return [
                    'id' => $alert->id,
                    'channel' => $alert->channel,
                    'severity' => $alert->severity,
                    'severity_emoji' => $severityEmoji,
                    'severity_bg' => $severityBg,
                    'message' => $alert->message,
                    'created_at' => \Carbon\Carbon::parse($alert->created_at)->diffForHumans(),
                ];
            })
            ->toArray();
    }
}