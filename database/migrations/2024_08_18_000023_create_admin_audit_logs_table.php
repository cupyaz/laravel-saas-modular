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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Admin performing the action
            $table->foreignId('admin_user_id')->constrained('users');
            
            // Target of the action
            $table->string('target_type')->nullable(); // User, Tenant, etc.
            $table->unsignedBigInteger('target_id')->nullable();
            $table->index(['target_type', 'target_id']);
            
            // Action details
            $table->string('action'); // user_created, user_updated, user_suspended, etc.
            $table->string('description');
            $table->string('severity')->default('info'); // info, warning, critical
            
            // Request context
            $table->ipAddress('ip_address');
            $table->text('user_agent');
            $table->string('request_method')->nullable();
            $table->text('request_url')->nullable();
            
            // Data changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('additional_data')->nullable();
            
            // Tenant context (for multi-tenant operations)
            $table->foreignId('tenant_id')->nullable()->constrained('tenants');
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index('admin_user_id');
            $table->index('action');
            $table->index('severity');
            $table->index('created_at');
            $table->index(['admin_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};