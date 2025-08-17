<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usage records table for detailed tracking
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('feature'); // e.g., 'basic_reports', 'file_storage'
            $table->string('metric'); // e.g., 'reports_per_month', 'storage_mb'
            $table->decimal('amount', 10, 2); // amount consumed
            $table->string('period'); // e.g., 'monthly', 'daily'
            $table->date('period_date'); // the period this usage belongs to
            $table->json('metadata')->nullable(); // additional context data
            $table->timestamps();

            $table->index(['tenant_id', 'feature', 'metric', 'period_date']);
            $table->index(['period_date', 'created_at']);
        });

        // Usage summaries table for aggregated data and performance
        Schema::create('usage_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('feature');
            $table->string('metric');
            $table->string('period');
            $table->date('period_date');
            $table->decimal('total_usage', 12, 2);
            $table->decimal('limit_value', 12, 2); // -1 for unlimited
            $table->decimal('percentage_used', 5, 2);
            $table->boolean('limit_exceeded')->default(false);
            $table->timestamp('last_updated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'feature', 'metric', 'period', 'period_date']);
            $table->index(['tenant_id', 'limit_exceeded']);
            $table->index(['period_date', 'percentage_used']);
        });

        // Usage alerts table for notifications
        Schema::create('usage_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('feature');
            $table->string('metric');
            $table->enum('alert_type', ['warning', 'limit_reached', 'limit_exceeded']);
            $table->decimal('threshold_percentage', 5, 2); // e.g., 80.00 for 80%
            $table->decimal('current_usage', 12, 2);
            $table->decimal('limit_value', 12, 2);
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->json('notification_data')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_sent']);
            $table->index(['alert_type', 'created_at']);
        });

        // Usage events table for real-time tracking
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('feature');
            $table->string('metric');
            $table->decimal('amount', 10, 2);
            $table->string('event_type'); // 'increment', 'decrement', 'reset'
            $table->json('context')->nullable(); // user_id, request_id, etc.
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['feature', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('usage_alerts');
        Schema::dropIfExists('usage_summaries');
        Schema::dropIfExists('usage_records');
    }
};