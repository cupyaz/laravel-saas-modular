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
                    'basic_features',
                    'limited_storage',
                    'community_support'
                ]),
                'limits' => json_encode([
                    'storage' => '1GB',
                    'users' => 3,
                    'projects' => 1
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro-monthly',
                'description' => 'Advanced features for growing businesses',
                'price' => 29.99,
                'billing_period' => 'monthly',
                'features' => json_encode([
                    'all_features',
                    'unlimited_storage',
                    'priority_support',
                    'advanced_analytics'
                ]),
                'limits' => json_encode([
                    'storage' => 'unlimited',
                    'users' => 50,
                    'projects' => 'unlimited'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pro (Yearly)',
                'slug' => 'pro-yearly',
                'description' => 'Advanced features with yearly discount',
                'price' => 299.99,
                'billing_period' => 'yearly',
                'features' => json_encode([
                    'all_features',
                    'unlimited_storage',
                    'priority_support',
                    'advanced_analytics',
                    'yearly_discount'
                ]),
                'limits' => json_encode([
                    'storage' => 'unlimited',
                    'users' => 50,
                    'projects' => 'unlimited'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for large organizations',
                'price' => 99.99,
                'billing_period' => 'monthly',
                'features' => json_encode([
                    'all_features',
                    'unlimited_storage',
                    'dedicated_support',
                    'custom_integrations',
                    'sla_guarantee'
                ]),
                'limits' => json_encode([
                    'storage' => 'unlimited',
                    'users' => 'unlimited',
                    'projects' => 'unlimited'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('plans')->insert($plans);
    }
}