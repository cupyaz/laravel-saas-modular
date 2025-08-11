<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for getting started with basic features',
                'price' => 0.00,
                'billing_period' => 'monthly',
                'features' => json_encode([
                    'basic_dashboard',
                    'profile_management',
                    'basic_reports',
                    'file_storage',
                    'team_members',
                    'projects'
                ]),
                'limits' => json_encode([
                    'reports_per_month' => 3,
                    'storage_mb' => 100,
                    'max_members' => 1,
                    'max_projects' => 3,
                ]),
                'is_active' => true,
                'trial_days' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'For small teams getting serious about their work',
                'price' => 9.99,
                'billing_period' => 'monthly',
                'features' => json_encode([
                    'basic_dashboard',
                    'profile_management',
                    'basic_reports',
                    'file_storage',
                    'team_members',
                    'projects',
                    'api_access',
                    'export_data'
                ]),
                'limits' => json_encode([
                    'reports_per_month' => 10,
                    'storage_mb' => 1000,
                    'max_members' => 5,
                    'max_projects' => 10,
                    'api_calls_per_month' => 1000,
                ]),
                'is_active' => true,
                'trial_days' => 14,
                'stripe_price_id' => 'price_basic_monthly',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'For growing businesses that need advanced features',
                'price' => 29.99,
                'billing_period' => 'monthly',
                'features' => json_encode([
                    'basic_dashboard',
                    'profile_management',
                    'basic_reports',
                    'file_storage',
                    'team_members',
                    'projects',
                    'api_access',
                    'export_data',
                    'advanced_analytics',
                    'custom_branding',
                    'priority_support',
                    'advanced_integrations',
                    'custom_fields'
                ]),
                'limits' => json_encode([
                    'reports_per_month' => -1,
                    'storage_mb' => 10000,
                    'max_members' => 20,
                    'max_projects' => 50,
                    'api_calls_per_month' => 10000,
                ]),
                'is_active' => true,
                'trial_days' => 14,
                'stripe_price_id' => 'price_premium_monthly',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations with custom needs',
                'price' => 99.99,
                'billing_period' => 'monthly',
                'features' => json_encode([
                    'basic_dashboard',
                    'profile_management',
                    'basic_reports',
                    'file_storage',
                    'team_members',
                    'projects',
                    'api_access',
                    'export_data',
                    'advanced_analytics',
                    'custom_branding',
                    'priority_support',
                    'advanced_integrations',
                    'custom_fields',
                    'white_labeling',
                    'sso_integration',
                    'audit_logs'
                ]),
                'limits' => json_encode([
                    'reports_per_month' => -1,
                    'storage_mb' => -1,
                    'max_members' => -1,
                    'max_projects' => -1,
                    'api_calls_per_month' => -1,
                ]),
                'is_active' => true,
                'trial_days' => 30,
                'stripe_price_id' => 'price_enterprise_monthly',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('plans')->insert($plans);
    }
}