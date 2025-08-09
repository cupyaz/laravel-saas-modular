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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // registration_attempt, login_attempt, verification_attempt, etc.
            $table->enum('status', ['success', 'failure', 'blocked', 'suspicious'])->default('success');
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('session_id')->nullable();
            $table->string('email')->nullable(); // Store email for failed attempts
            $table->text('reason')->nullable(); // Reason for failure/block
            $table->timestamp('created_at');
            
            // Indexes for performance and security analysis
            $table->index(['event_type', 'status', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['user_id', 'event_type', 'created_at']);
            $table->index(['email', 'event_type', 'created_at']);
            $table->index('created_at'); // For cleanup jobs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};