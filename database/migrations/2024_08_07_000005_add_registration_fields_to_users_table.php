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
            // Profile information
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable()->after('date_of_birth');
            $table->string('company')->nullable()->after('gender');
            $table->string('job_title')->nullable()->after('company');
            $table->text('bio')->nullable()->after('job_title');
            
            // Location information
            $table->string('country', 2)->nullable()->after('bio'); // ISO 3166-1 alpha-2
            $table->string('timezone')->nullable()->after('country');
            
            // GDPR and consent tracking
            $table->boolean('gdpr_consent')->default(false)->after('timezone');
            $table->timestamp('gdpr_consent_at')->nullable()->after('gdpr_consent');
            $table->ipAddress('gdpr_consent_ip')->nullable()->after('gdpr_consent_at');
            $table->boolean('marketing_consent')->default(false)->after('gdpr_consent_ip');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            
            // Security and verification
            $table->string('email_verification_token')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verification_token');
            $table->ipAddress('registration_ip')->nullable()->after('email_verification_sent_at');
            $table->text('registration_user_agent')->nullable()->after('registration_ip');
            
            // Account status and preferences
            $table->boolean('onboarding_completed')->default(false)->after('is_active');
            $table->json('preferences')->nullable()->after('onboarding_completed');
            
            // Security tracking
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('last_login_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            
            // Add indexes for performance
            $table->index(['email_verified_at', 'is_active']);
            $table->index(['country', 'is_active']);
            $table->index('gdpr_consent');
            $table->index('onboarding_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_verified_at', 'is_active']);
            $table->dropIndex(['country', 'is_active']);
            $table->dropIndex(['gdpr_consent']);
            $table->dropIndex(['onboarding_completed']);
            
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'date_of_birth',
                'gender',
                'company',
                'job_title',
                'bio',
                'country',
                'timezone',
                'gdpr_consent',
                'gdpr_consent_at',
                'gdpr_consent_ip',
                'marketing_consent',
                'marketing_consent_at',
                'email_verification_token',
                'email_verification_sent_at',
                'registration_ip',
                'registration_user_agent',
                'onboarding_completed',
                'preferences',
                'failed_login_attempts',
                'locked_until'
            ]);
        });
    }
};