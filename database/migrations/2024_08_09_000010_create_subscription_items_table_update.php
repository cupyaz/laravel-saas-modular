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
        // Update subscription_items table for Cashier compatibility
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('type')->default('default')->after('subscription_id');
            
            // Drop existing unique constraint
            $table->dropUnique(['subscription_id', 'stripe_price_id']);
            
            // Add new unique constraint that includes type
            $table->unique(['subscription_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'type']);
            $table->dropColumn('type');
            $table->unique(['subscription_id', 'stripe_price_id']);
        });
    }
};