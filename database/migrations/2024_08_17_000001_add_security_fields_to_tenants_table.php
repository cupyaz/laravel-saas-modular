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
        Schema::table('tenants', function (Blueprint $table) {
            // Multi-tenant security fields
            $table->text('encryption_key')->nullable()->after('tax_id');
            $table->string('data_residency', 50)->nullable()->after('encryption_key');
            $table->json('compliance_flags')->nullable()->after('data_residency');
            $table->json('resource_limits')->nullable()->after('compliance_flags');
            $table->string('isolation_level', 50)->default('database')->after('resource_limits');
            $table->json('security_settings')->nullable()->after('isolation_level');
            $table->boolean('audit_enabled')->default(true)->after('security_settings');
            $table->json('backup_settings')->nullable()->after('audit_enabled');
            
            // Add indexes for performance
            $table->index('data_residency');
            $table->index('isolation_level');
            $table->index('audit_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['data_residency']);
            $table->dropIndex(['isolation_level']);
            $table->dropIndex(['audit_enabled']);
            
            $table->dropColumn([
                'encryption_key',
                'data_residency',
                'compliance_flags',
                'resource_limits',
                'isolation_level',
                'security_settings',
                'audit_enabled',
                'backup_settings',
            ]);
        });
    }
};