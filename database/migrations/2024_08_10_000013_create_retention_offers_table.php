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
        Schema::create('retention_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('offer_type'); // discount, free_months, plan_downgrade
            $table->string('discount_type')->nullable(); // percentage, fixed_amount
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->integer('free_months')->nullable();
            $table->foreignId('downgrade_plan_id')->nullable()->constrained('plans');
            $table->text('offer_description');
            $table->timestamp('valid_until');
            $table->boolean('is_accepted')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('terms')->nullable(); // Additional terms and conditions
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            // Indexes for performance
            $table->index(['subscription_id', 'is_accepted']);
            $table->index(['valid_until']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retention_offers');
    }
};