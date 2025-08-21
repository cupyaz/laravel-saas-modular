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
        // Laravel's built-in notifications table (extended)
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                
                $table->index(['notifiable_type', 'notifiable_id']);
            });
        }

        // Notification templates for consistent messaging
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // subscription_created, ticket_replied, etc.
            $table->string('title'); // Human readable title
            $table->string('category'); // subscription, support, security, billing
            $table->json('channels'); // ['database', 'email', 'sms'] - available channels
            $table->json('default_channels'); // Default enabled channels
            $table->text('subject')->nullable(); // Email subject template
            $table->text('email_template')->nullable(); // Email HTML template
            $table->text('sms_template')->nullable(); // SMS message template
            $table->text('push_template')->nullable(); // Push notification template
            $table->text('database_template')->nullable(); // In-app notification template
            $table->json('variables')->nullable(); // Available template variables
            $table->boolean('is_system')->default(false); // System template (cannot be disabled)
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(5); // 1=highest, 10=lowest
            $table->json('conditions')->nullable(); // Conditions for sending
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['name', 'is_active']);
        });

        // User notification preferences
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id');
            $table->json('enabled_channels'); // Channels user wants for this notification
            $table->boolean('is_enabled')->default(true);
            $table->json('conditions')->nullable(); // User-specific conditions
            $table->string('frequency')->default('immediate'); // immediate, daily, weekly
            $table->time('preferred_time')->nullable(); // For digest notifications
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('notification_templates')->onDelete('cascade');
            $table->unique(['user_id', 'template_id']);
            $table->index(['user_id', 'is_enabled']);
        });

        // Notification channels configuration
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // database, email, sms, push, slack, webhook
            $table->string('driver'); // database, mail, nexmo, fcm, slack, webhook
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable(); // Channel-specific configuration
            $table->integer('rate_limit')->nullable(); // Max notifications per minute
            $table->boolean('supports_batching')->default(false);
            $table->integer('batch_size')->nullable();
            $table->integer('retry_attempts')->default(3);
            $table->integer('retry_delay')->default(60); // seconds
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active']);
        });

        // Notification delivery logs for analytics
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('template_name');
            $table->string('channel'); // database, email, sms, push
            $table->string('status'); // pending, sent, failed, bounced, delivered
            $table->text('recipient'); // email, phone number, device token
            $table->json('payload')->nullable(); // The actual notification data sent
            $table->text('response')->nullable(); // Response from delivery service
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable(); // Email opened, notification tapped
            $table->timestamp('clicked_at')->nullable(); // Link clicked
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();

            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index(['template_name', 'channel']);
            $table->index(['status', 'created_at']);
            $table->index(['sent_at', 'delivered_at']);
        });

        // Notification digests for batched notifications
        Schema::create('notification_digests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('frequency'); // daily, weekly, monthly
            $table->string('category')->nullable(); // Filter by category
            $table->json('notification_ids'); // Array of notification IDs
            $table->json('summary_data'); // Aggregated data for the digest
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'frequency']);
            $table->index(['status', 'created_at']);
        });

        // Real-time notification broadcasting
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name'); // user.123, tenant.456, public
            $table->string('event_name'); // notification.created, status.updated
            $table->json('payload'); // Data to broadcast
            $table->string('status')->default('pending'); // pending, broadcasting, completed, failed
            $table->timestamp('broadcast_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['channel_name', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Notification queue jobs for batching and scheduling
        Schema::create('notification_queue_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type'); // immediate, scheduled, batch, digest
            $table->string('template_name');
            $table->json('recipient_ids'); // User IDs to send to
            $table->json('payload'); // Notification data
            $table->json('channels'); // Channels to send via
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['job_type', 'status']);
        });

        // Notification analytics and metrics
        Schema::create('notification_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('template_name');
            $table->string('channel');
            $table->string('metric_type'); // sent, delivered, opened, clicked, unsubscribed
            $table->integer('count')->default(0);
            $table->decimal('rate', 5, 2)->nullable(); // Delivery rate, open rate, etc.
            $table->json('metadata')->nullable(); // Additional metrics data
            $table->timestamps();

            $table->unique(['date', 'template_name', 'channel', 'metric_type']);
            $table->index(['template_name', 'date']);
            $table->index(['channel', 'metric_type']);
        });

        // User notification settings (global preferences)
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('notifications_enabled')->default(true);
            $table->json('global_channels')->nullable(); // Globally disabled channels
            $table->boolean('marketing_enabled')->default(true);
            $table->boolean('product_updates_enabled')->default(true);
            $table->boolean('security_alerts_enabled')->default(true);
            $table->string('digest_frequency')->default('daily'); // none, daily, weekly
            $table->time('digest_time')->default('09:00'); // Preferred time for digests
            $table->string('timezone')->default('UTC');
            $table->boolean('do_not_disturb_enabled')->default(false);
            $table->time('dnd_start_time')->nullable();
            $table->time('dnd_end_time')->nullable();
            $table->json('dnd_days')->nullable(); // Days of week for DND
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
        Schema::dropIfExists('notification_analytics');
        Schema::dropIfExists('notification_queue_jobs');
        Schema::dropIfExists('notification_broadcasts');
        Schema::dropIfExists('notification_digests');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notifications');
    }
};