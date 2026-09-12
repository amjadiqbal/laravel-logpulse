<?php

// src/LogPulseManager.php

namespace AmjadIqbal\LogPulse;

use AmjadIqbal\LogPulse\Analyzers\BurdenCalculator;
use AmjadIqbal\LogPulse\Analyzers\FrequencyAnalyzer;
use AmjadIqbal\LogPulse\Analyzers\PatternMatcher;
use AmjadIqbal\LogPulse\Models\LogPulseEvent;
use Illuminate\Support\Collection;

class LogPulseManager
{
    protected BurdenCalculator $burdenCalculator;
    protected FrequencyAnalyzer $frequencyAnalyzer;
    protected PatternMatcher $patternMatcher;

    public function __construct()
    {
        $this->burdenCalculator = new BurdenCalculator();
        $this->frequencyAnalyzer = new FrequencyAnalyzer();
        $this->patternMatcher = new PatternMatcher();
    }

    /**
     * Get the current system burden score.
     */
    public function getBurdenScore(): int
    {
        $events = $this->getRecentEvents();
        return $this->burdenCalculator->calculate($events);
    }

    /**
     * Get health status summary.
     */
    public function getHealthStatus(): array
    {
        $score = $this->getBurdenScore();
        $interpretation = $this->burdenCalculator->getInterpretation($score);

        return array_merge(['score' => $score], $interpretation);
    }

    /**
     * Get top errors.
     */
    public function getTopErrors(int $limit = 10): Collection
    {
        return $this->frequencyAnalyzer->getTopExceptions($limit);
    }

    /**
     * Detect patterns in recent events.
     */
    public function detectPatterns(): array
    {
        $events = $this->getRecentEvents(15); // Last 15 minutes
        return $this->patternMatcher->analyze($events);
    }

    /**
     * Get events from the last N minutes.
     */
    protected function getRecentEvents(int $minutes = 30): Collection
    {
        return LogPulseEvent::inLastMinutes($minutes)->get();
    }
}