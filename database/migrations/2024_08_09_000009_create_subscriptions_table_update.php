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
        // Update existing subscriptions table to match Cashier requirements
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('type')->default('default')->after('id');
            $table->integer('quantity')->default(1)->after('stripe_subscription_id');
            
            // Rename tenant_id to use morphs for billable
            $table->dropForeign(['tenant_id']);
            $table->renameColumn('tenant_id', 'billable_id');
            $table->string('billable_type')->after('billable_id');
            
            $table->index(['billable_id', 'billable_type']);
        });

        // Update existing records to use morphs
        DB::table('subscriptions')->update([
            'billable_type' => 'App\\Models\\Tenant'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['billable_id', 'billable_type']);
            $table->dropColumn(['type', 'quantity', 'billable_type']);
            $table->renameColumn('billable_id', 'tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }
};