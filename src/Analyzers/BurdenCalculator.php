<?php

// src/Analyzers/BurdenCalculator.php

namespace AmjadIqbal\LogPulse\Analyzers;

use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Support\Collection;

class BurdenCalculator
{
    protected array $weights;
    protected Collection $events;

    public function __construct()
    {
        $this->weights = config('logpulse.burden_score', [
            'severity_weight' => 0.3,
            'frequency_weight' => 0.4,
            'resource_impact_weight' => 0.3,
        ]);
    }

    /**
     * Calculate the System Burden Score for a collection of events.
     */
    public function calculate(Collection $events): int
    {
        $this->events = $events;

        if ($events->isEmpty()) {
            return 0;
        }

        $severityScore = $this->calculateSeverityScore();
        $frequencyScore = $this->calculateFrequencyScore();
        $resourceImpactScore = $this->calculateResourceImpactScore();

        $totalScore = (
            ($severityScore * $this->weights['severity_weight']) +
            ($frequencyScore * $this->weights['frequency_weight']) +
            ($resourceImpactScore * $this->weights['resource_impact_weight'])
        ) * 100;

        return min(100, (int) round($totalScore));
    }

    /**
     * Calculate severity based on exception types and their impact.
     */
    protected function calculateSeverityScore(): float
    {
        $severityMap = [
            'critical' => 1.0,
            'error' => 0.8,
            'warning' => 0.5,
            'info' => 0.2,
            'debug' => 0.1,
        ];

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($this->events as $event) {
            $severity = $event->severity ?? 'error';
            $weight = $severityMap[$severity] ?? 0.5;
            $weightedSum += $weight * $event->occurrence_count;
            $totalWeight += $event->occurrence_count;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }

    /**
     * Calculate frequency score based on event occurrence rates.
     */
    protected function calculateFrequencyScore(): float
    {
        $totalOccurrences = $this->events->sum('occurrence_count');
        
        // Normalize based on expected thresholds
        $criticalThreshold = config('logpulse.thresholds.critical.count', 10);
        
        if ($totalOccurrences <= 0) {
            return 0;
        }

        // Logarithmic scale to prevent extreme values
        $ratio = $totalOccurrences / max(1, $criticalThreshold);
        $score = log($ratio + 1, 2) / log(11, 2); // log base 2, normalized to 0-1
        
        return min(1.0, $score);
    }

    /**
     * Calculate resource impact based on exception types.
     */
    protected function calculateResourceImpactScore(): float
    {
        $highImpactExceptions = [
            'PDOException',
            'Illuminate\Database\QueryException',
            'Predis\Connection\ConnectionException',
            'RedisException',
            'GuzzleHttp\Exception\ConnectException',
        ];

        $highImpactCount = 0;
        $totalCount = 0;

        foreach ($this->events as $event) {
            $isHighImpact = false;
            foreach ($highImpactExceptions as $exception) {
                if (str_contains($event->exception_class, $exception)) {
                    $isHighImpact = true;
                    break;
                }
            }

            if ($isHighImpact) {
                $highImpactCount += $event->occurrence_count;
            }
            $totalCount += $event->occurrence_count;
        }

        return $totalCount > 0 ? $highImpactCount / $totalCount : 0;
    }

    /**
     * Get a human-readable interpretation of the burden score.
     */
    public function getInterpretation(int $score): array
    {
        return match(true) {
            $score >= 80 => [
                'level' => 'critical',
                'color' => 'red',
                'emoji' => '🔴',
                'text' => 'CRITICAL — System under heavy stress',
                'action' => 'Immediate investigation required',
            ],
            $score >= 60 => [
                'level' => 'warning',
                'color' => 'orange',
                'emoji' => '🟠',
                'text' => 'WARNING — Elevated error rates detected',
                'action' => 'Investigate and prepare for potential escalation',
            ],
            $score >= 30 => [
                'level' => 'elevated',
                'color' => 'yellow',
                'emoji' => '🟡',
                'text' => 'ELEVATED — Above normal error rates',
                'action' => 'Monitor closely',
            ],
            default => [
                'level' => 'healthy',
                'color' => 'green',
                'emoji' => '🟢',
                'text' => 'HEALTHY — Normal operating levels',
                'action' => 'No action needed',
            ],
        };
    }
}