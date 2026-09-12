<?php

// src/Analyzers/PatternMatcher.php

namespace AmjadIqbal\LogPulse\Analyzers;

use Illuminate\Support\Collection;

class PatternMatcher
{
    protected array $patterns = [];

    public function __construct()
    {
        $this->registerDefaultPatterns();
    }

    /**
     * Register default detection patterns.
     */
    protected function registerDefaultPatterns(): void
    {
        $this->patterns = [
            'cascade_failure' => [
                'name' => 'Cascade Failure',
                'description' => 'Multiple different exceptions occurring in rapid succession',
                'detector' => fn (Collection $events) => $this->detectCascadeFailure($events),
                'severity' => 'critical',
            ],
            'retry_storm' => [
                'name' => 'Retry Storm',
                'description' => 'Same exception occurring at exponentially increasing rates',
                'detector' => fn (Collection $events) => $this->detectRetryStorm($events),
                'severity' => 'warning',
            ],
            'dependency_outage' => [
                'name' => 'Dependency Outage',
                'description' => 'Connection/timeout exceptions from external services',
                'detector' => fn (Collection $events) => $this->detectDependencyOutage($events),
                'severity' => 'critical',
            ],
            'resource_exhaustion' => [
                'name' => 'Resource Exhaustion',
                'description' => 'Memory, disk, or connection pool exhaustion pattern',
                'detector' => fn (Collection $events) => $this->detectResourceExhaustion($events),
                'severity' => 'critical',
            ],
            'degraded_performance' => [
                'name' => 'Degraded Performance',
                'description' => 'Increasing response times and timeout errors',
                'detector' => fn (Collection $events) => $this->detectDegradedPerformance($events),
                'severity' => 'warning',
            ],
        ];
    }

    /**
     * Analyze events for known patterns.
     */
    public function analyze(Collection $events): array
    {
        $detectedPatterns = [];

        foreach ($this->patterns as $key => $pattern) {
            if ($pattern['detector']($events)) {
                $detectedPatterns[] = [
                    'key' => $key,
                    'name' => $pattern['name'],
                    'description' => $pattern['description'],
                    'severity' => $pattern['severity'],
                ];
            }
        }

        return $detectedPatterns;
    }

    /**
     * Detect cascade failure: multiple different exceptions in quick succession.
     */
    protected function detectCascadeFailure(Collection $events): bool
    {
        $uniqueExceptions = $events->unique('exception_class')->count();
        // Events are pre-aggregated by (exception_class, route) with an occurrence_count —
        // the same field every other analyzer in this package (FrequencyAnalyzer,
        // BurdenCalculator) treats as the real error volume. Counting the collection itself
        // instead undercounts by however much aggregation already happened upstream: 4
        // aggregated rows totalling 14 occurrences would otherwise read as "4 events", never
        // reaching the threshold below no matter how much real traffic they represent.
        $totalEvents = $events->sum('occurrence_count');

        // Cascade failure: many unique exceptions in a short period
        return $uniqueExceptions >= 3 && $totalEvents >= 10;
    }

    /**
     * Detect retry storm: same exception at exponential rates.
     */
    protected function detectRetryStorm(Collection $events): bool
    {
        $grouped = $events->groupBy('exception_class');

        foreach ($grouped as $exceptionGroup) {
            if ($exceptionGroup->count() < 5) {
                continue;
            }

            $timestamps = $exceptionGroup->sortBy('created_at')
                ->pluck('created_at')
                ->toArray();

            // Check if intervals between events are decreasing (exponential backoff pattern)
            $intervals = [];
            for ($i = 1; $i < count($timestamps); $i++) {
                $intervals[] = $timestamps[$i]->diffInSeconds($timestamps[$i - 1]);
            }

            // Retry storm: intervals are getting shorter
            if (count($intervals) >= 3) {
                $firstHalf = array_slice($intervals, 0, (int) floor(count($intervals) / 2));
                $secondHalf = array_slice($intervals, (int) floor(count($intervals) / 2));

                $avgFirst = array_sum($firstHalf) / count($firstHalf);
                $avgSecond = array_sum($secondHalf) / count($secondHalf);

                if ($avgFirst > 0 && $avgSecond < $avgFirst * 0.5) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect dependency outage: connection/timeout exceptions.
     */
    protected function detectDependencyOutage(Collection $events): bool
    {
        $outageKeywords = [
            'ConnectionException',
            'ConnectException',
            'TimeoutException',
            'ConnectionRefused',
            'Connection timed out',
            'cURL error',
            'GuzzleHttp',
        ];

        $outageEvents = $events->filter(function ($event) use ($outageKeywords) {
            foreach ($outageKeywords as $keyword) {
                if (
                    str_contains($event->exception_class, $keyword) ||
                    str_contains($event->message, $keyword)
                ) {
                    return true;
                }
            }

            return false;
        });

        return $outageEvents->count() >= 5;
    }

    /**
     * Detect resource exhaustion patterns.
     */
    protected function detectResourceExhaustion(Collection $events): bool
    {
        $resourceKeywords = [
            'Allowed memory size',
            'disk full',
            'No space left on device',
            'Too many connections',
            'Connection pool',
            'max_connections',
            'Queue is full',
        ];

        foreach ($events as $event) {
            foreach ($resourceKeywords as $keyword) {
                if (str_contains($event->message, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detect degraded performance: increasing timeouts.
     */
    protected function detectDegradedPerformance(Collection $events): bool
    {
        $timeoutKeywords = [
            'timeout',
            'timed out',
            'Maximum execution time',
            '504 Gateway Timeout',
            'Request Timeout',
        ];

        $timeoutEvents = $events->filter(function ($event) use ($timeoutKeywords) {
            foreach ($timeoutKeywords as $keyword) {
                if (stripos($event->message, $keyword) !== false) {
                    return true;
                }
            }

            return false;
        });

        return $timeoutEvents->count() >= 3;
    }

    /**
     * Add a custom pattern detector.
     */
    public function addPattern(string $key, array $pattern): self
    {
        $this->patterns[$key] = $pattern;

        return $this;
    }

    /**
     * Get all registered patterns.
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
}
