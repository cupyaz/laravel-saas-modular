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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add subscription lifecycle fields
            $table->string('internal_status')->default('active')->after('status');
            $table->timestamp('paused_at')->nullable()->after('ends_at');
            $table->timestamp('grace_period_ends_at')->nullable()->after('paused_at');
            $table->text('cancellation_reason')->nullable()->after('grace_period_ends_at');
            $table->json('cancellation_feedback')->nullable()->after('cancellation_reason');
            $table->boolean('retention_offer_shown')->default(false)->after('cancellation_feedback');
            $table->timestamp('retention_offer_shown_at')->nullable()->after('retention_offer_shown');
            $table->json('metadata')->nullable()->after('retention_offer_shown_at');
            
            // Add indexes for performance
            $table->index(['internal_status', 'paused_at']);
            $table->index(['grace_period_ends_at']);
            $table->index(['retention_offer_shown', 'retention_offer_shown_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'internal_status',
                'paused_at',
                'grace_period_ends_at',
                'cancellation_reason',
                'cancellation_feedback',
                'retention_offer_shown',
                'retention_offer_shown_at',
                'metadata'
            ]);
        });
    }
};