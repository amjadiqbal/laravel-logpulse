<?php

// src/Models/LogPulseEvent.php

namespace AmjadIqbal\LogPulse\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The table name is set dynamically in the constructor from config
 * (logpulse.storage.table), so Larastan can't discover these columns from a real
 * migration/`$table` the way it normally would — hence the explicit @property block.
 *
 * @property int $id
 * @property string $aggregate_id
 * @property string $exception_class
 * @property string $severity
 * @property string $message
 * @property string|null $route
 * @property string|null $file
 * @property int|null $line
 * @property string|null $stack_trace
 * @property array<string,mixed>|null $context
 * @property array<string,mixed>|null $request_data
 * @property int $occurrence_count
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property string $alert_status
 * @property \Illuminate\Support\Carbon|null $alerted_at
 * @property string|null $alert_channel
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class LogPulseEvent extends Model
{
    protected $table;

    protected $fillable = [
        'aggregate_id',
        'exception_class',
        'severity',
        'message',
        'route',
        'file',
        'line',
        'stack_trace',
        'context',
        'request_data',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'alert_status',
        'alerted_at',
        'alert_channel',
    ];

    protected $casts = [
        'context' => 'array',
        'request_data' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'alerted_at' => 'datetime',
        'occurrence_count' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('logpulse.storage.table', 'logpulse_events');
    }

    /**
     * Scope: Events in the last X minutes.
     */
    public function scopeInLastMinutes(Builder $query, int $minutes): Builder
    {
        return $query->where('last_seen_at', '>=', Carbon::now()->subMinutes($minutes));
    }

    /**
     * Scope: Events with a specific severity.
     */
    public function scopeOfSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: Events that haven't been alerted yet.
     */
    public function scopeNotAlerted(Builder $query): Builder
    {
        return $query->whereNull('alerted_at');
    }

    /**
     * Scope: Events for a specific route.
     */
    public function scopeForRoute(Builder $query, string $route): Builder
    {
        return $query->where('route', $route);
    }

    /**
     * Get the exception class without namespace.
     */
    public function getShortExceptionAttribute(): string
    {
        $parts = explode('\\', $this->exception_class);

        return end($parts);
    }

    /**
     * Get formatted stack trace (first few lines).
     */
    public function getShortTraceAttribute(): string
    {
        if (empty($this->stack_trace)) {
            return '';
        }

        $lines = explode("\n", $this->stack_trace);

        return implode("\n", array_slice($lines, 0, 3));
    }

    /**
     * Check if this event is critical based on thresholds.
     */
    public function isCritical(): bool
    {
        $threshold = config('logpulse.thresholds.critical.count', 10);

        return $this->occurrence_count >= $threshold;
    }
}
