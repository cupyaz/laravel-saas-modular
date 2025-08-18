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
        Schema::table('users', function (Blueprint $table) {
            // Admin role system
            $table->string('role')->default('user')->after('is_active');
            $table->json('permissions')->nullable()->after('role');
            $table->boolean('is_super_admin')->default(false)->after('permissions');
            
            // Admin-specific fields
            $table->timestamp('last_admin_login_at')->nullable()->after('last_login_at');
            $table->timestamp('password_expires_at')->nullable()->after('last_admin_login_at');
            $table->boolean('requires_2fa')->default(false)->after('password_expires_at');
            $table->string('admin_notes')->nullable()->after('requires_2fa');
            
            // Admin activity tracking
            $table->timestamp('suspended_at')->nullable()->after('admin_notes');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->after('suspension_reason');
            
            // Indexes for performance
            $table->index(['role', 'is_active']);
            $table->index(['is_super_admin', 'is_active']);
            $table->index('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['is_super_admin', 'is_active']);
            $table->dropIndex(['suspended_at']);
            
            $table->dropColumn([
                'role',
                'permissions',
                'is_super_admin',
                'last_admin_login_at',
                'password_expires_at',
                'requires_2fa',
                'admin_notes',
                'suspended_at',
                'suspension_reason',
                'suspended_by',
            ]);
        });
    }
};