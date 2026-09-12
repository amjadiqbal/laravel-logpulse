<?php

// database/migrations/2024_01_01_000000_create_logpulse_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('logpulse.storage.table', 'logpulse_events'), function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_id')->index();
            $table->string('exception_class');
            $table->string('severity')->default('error');
            $table->text('message');
            $table->string('route')->nullable()->index();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->json('context')->nullable();
            $table->json('request_data')->nullable();
            $table->integer('occurrence_count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('alert_status')->default('pending');
            $table->timestamp('alerted_at')->nullable();
            $table->string('alert_channel')->nullable();
            $table->timestamps();

            $table->index(['exception_class', 'route']);
            $table->index(['severity', 'created_at']);
            $table->index('last_seen_at');
        });

        // Create a table for alert history
        Schema::create('logpulse_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('severity');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Create a table for circuit breaker state
        Schema::create('logpulse_circuit_breaker', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->unique();
            $table->integer('alert_count')->default(0);
            $table->timestamp('window_started_at');
            $table->boolean('is_open')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->float('current_backoff')->default(1.0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('logpulse.storage.table', 'logpulse_events'));
        Schema::dropIfExists('logpulse_alerts');
        Schema::dropIfExists('logpulse_circuit_breaker');
    }
};