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
        // Upgrade prompt templates and configurations
        Schema::create('upgrade_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Internal name for the prompt
            $table->string('type'); // 'usage_limit', 'feature_gate', 'trial_ending', etc.
            $table->string('trigger_condition'); // JSON query for when to show
            $table->json('content'); // Message, title, CTA text, etc.
            $table->json('targeting_rules'); // Who should see this prompt
            $table->string('placement'); // 'modal', 'banner', 'inline', 'sidebar'
            $table->integer('priority')->default(0); // Higher priority shows first
            $table->boolean('is_active')->default(true);
            $table->integer('max_displays_per_user')->default(3);
            $table->integer('cooldown_hours')->default(24); // Time between displays
            $table->json('ab_test_config')->nullable(); // A/B testing configuration
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['priority', 'is_active']);
        });

        // Track when prompts are displayed to users
        Schema::create('upgrade_prompt_displays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('upgrade_prompt_id')->constrained()->onDelete('cascade');
            $table->string('variant')->nullable(); // A/B test variant shown
            $table->json('context'); // Usage context when shown
            $table->string('placement_location'); // Where it was displayed
            $table->enum('action_taken', ['dismissed', 'clicked', 'converted', 'ignored'])->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'upgrade_prompt_id']);
            $table->index(['created_at', 'action_taken']);
        });

        // Track successful conversions from prompts
        Schema::create('upgrade_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('upgrade_prompt_display_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignId('to_plan_id')->constrained('plans')->onDelete('cascade');
            $table->decimal('conversion_value', 10, 2); // Revenue from conversion
            $table->json('conversion_data'); // Additional conversion context
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['upgrade_prompt_display_id']);
        });

        // A/B testing variants for prompts
        Schema::create('ab_test_variants', function (Blueprint $table) {
            $table->id();
            $table->string('test_name'); // Name of the A/B test
            $table->string('variant_name'); // 'control', 'variant_a', 'variant_b', etc.
            $table->json('configuration'); // Variant-specific config
            $table->integer('traffic_percentage'); // % of users in this variant
            $table->boolean('is_active')->default(true);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('success_metrics'); // Metrics to track
            $table->timestamps();

            $table->unique(['test_name', 'variant_name']);
            $table->index(['test_name', 'is_active']);
        });

        // User assignments to A/B test variants
        Schema::create('ab_test_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('test_name');
            $table->string('variant_name');
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'test_name']);
            $table->index(['test_name', 'variant_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ab_test_assignments');
        Schema::dropIfExists('ab_test_variants');
        Schema::dropIfExists('upgrade_conversions');
        Schema::dropIfExists('upgrade_prompt_displays');
        Schema::dropIfExists('upgrade_prompts');
    }
};