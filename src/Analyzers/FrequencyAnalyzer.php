<?php

// src/Analyzers/FrequencyAnalyzer.php

namespace AmjadIqbal\LogPulse\Analyzers;

use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FrequencyAnalyzer
{
    protected array $thresholds;

    protected array $windowSizes;

    public function __construct()
    {
        $this->thresholds = config('logpulse.thresholds', [
            'critical' => ['count' => 10, 'window' => 5],
            'warning' => ['count' => 50, 'window' => 15],
            'info' => ['count' => 100, 'window' => 30],
        ]);
    }

    /**
     * Analyze event frequency and determine if thresholds are breached.
     */
    public function analyze(string $exceptionClass, ?string $route = null): array
    {
        $results = [];

        foreach ($this->thresholds as $severity => $config) {
            $windowMinutes = $config['window'];
            $thresholdCount = $config['count'];

            $count = $this->countEventsInWindow($exceptionClass, $route, $windowMinutes);

            if ($count >= $thresholdCount) {
                $results[$severity] = [
                    'triggered' => true,
                    'count' => $count,
                    'threshold' => $thresholdCount,
                    'window_minutes' => $windowMinutes,
                    'exceeded_by' => $count - $thresholdCount,
                    'percentage_over' => round((($count - $thresholdCount) / $thresholdCount) * 100, 1),
                ];
            }
        }

        return $results;
    }

    /**
     * Count events of a specific type within a time window.
     */
    protected function countEventsInWindow(string $exceptionClass, ?string $route, int $windowMinutes): int
    {
        $since = Carbon::now()->subMinutes($windowMinutes);

        $query = LogPulseEvent::where('exception_class', $exceptionClass)
            ->where('last_seen_at', '>=', $since);

        if ($route) {
            $query->where('route', $route);
        }

        return $query->sum('occurrence_count');
    }

    /**
     * Get the trend direction for an exception.
     */
    public function getTrend(string $exceptionClass, ?string $route = null): array
    {
        $now = Carbon::now();

        $currentWindow = $this->countEventsInWindow(
            $exceptionClass,
            $route,
            5
        );

        $previousWindow = LogPulseEvent::where('exception_class', $exceptionClass)
            ->where('last_seen_at', '>=', $now->copy()->subMinutes(10))
            ->where('last_seen_at', '<', $now->copy()->subMinutes(5))
            ->when($route, fn ($q) => $q->where('route', $route))
            ->sum('occurrence_count');

        if ($currentWindow === 0 && $previousWindow === 0) {
            return ['direction' => 'stable', 'icon' => '→', 'change_percent' => 0];
        }

        if ($previousWindow === 0) {
            return ['direction' => 'new', 'icon' => '🆕', 'change_percent' => 100];
        }

        $change = (($currentWindow - $previousWindow) / $previousWindow) * 100;

        return [
            'direction' => $change > 10 ? 'increasing' : ($change < -10 ? 'decreasing' : 'stable'),
            'icon' => $change > 10 ? '↑' : ($change < -10 ? '↓' : '→'),
            'change_percent' => round($change, 1),
            'current_count' => $currentWindow,
            'previous_count' => $previousWindow,
        ];
    }

    /**
     * Get top exceptions by frequency in the last window.
     */
    public function getTopExceptions(int $limit = 10, int $windowMinutes = 5): Collection
    {
        $since = Carbon::now()->subMinutes($windowMinutes);

        return LogPulseEvent::where('last_seen_at', '>=', $since)
            ->selectRaw('exception_class, route, SUM(occurrence_count) as total_count, MAX(last_seen_at) as last_seen')
            ->groupBy('exception_class', 'route')
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                $trend = $this->getTrend($event->exception_class, $event->route);

                // total_count and last_seen are selectRaw() aggregate aliases, not real
                // model columns, so they're not in LogPulseEvent's @property block and
                // PHPStan can't know they exist on the returned rows.
                return [
                    'exception_class' => $event->exception_class,
                    'route' => $event->route ?? 'N/A',
                    // @phpstan-ignore property.notFound
                    'count' => $event->total_count,
                    // $event->last_seen is a plain string here, not the Carbon instance the
                    // model's $casts would give a real last_seen_at column.
                    // @phpstan-ignore property.notFound
                    'last_seen_at' => Carbon::parse($event->last_seen)->diffForHumans(),
                    'trend_direction' => $trend['direction'],
                    'trend_icon' => $trend['icon'],
                ];
            });
    }
}
