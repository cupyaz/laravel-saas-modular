<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UpgradePrompt;
use App\Models\ABTestVariant;

class UpgradePromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usage limit prompts
        UpgradePrompt::create([
            'name' => 'Reports Limit Reached',
            'type' => 'usage_limit',
            'trigger_condition' => [
                'usage_limit_reached' => [
                    'feature' => 'basic_reports',
                    'metric' => 'reports_per_month',
                    'percentage' => 80
                ]
            ],
            'content' => [
                'title' => 'You\'re running out of reports!',
                'message' => 'You\'ve used 80% of your monthly report limit. Upgrade to unlimited reports.',
                'cta_text' => 'Upgrade Now',
                'benefits' => [
                    'Unlimited report generation',
                    'Advanced analytics',
                    'Custom templates'
                ]
            ],
            'targeting_rules' => [
                'plan_slugs' => ['free']
            ],
            'placement' => 'modal',
            'priority' => 100,
            'max_displays_per_user' => 3,
            'cooldown_hours' => 48,
        ]);

        UpgradePrompt::create([
            'name' => 'Storage Limit Warning',
            'type' => 'usage_limit',
            'trigger_condition' => [
                'usage_limit_reached' => [
                    'feature' => 'file_storage',
                    'metric' => 'storage_mb',
                    'percentage' => 90
                ]
            ],
            'content' => [
                'title' => 'Storage almost full',
                'message' => 'You\'re at 90% of your storage limit. Upgrade for more space.',
                'cta_text' => 'Get More Storage',
                'benefits' => [
                    '10x more storage space',
                    'File versioning',
                    'Team sharing'
                ]
            ],
            'targeting_rules' => [
                'plan_slugs' => ['free', 'basic']
            ],
            'placement' => 'banner',
            'priority' => 90,
            'max_displays_per_user' => 5,
            'cooldown_hours' => 24,
        ]);

        // Feature access prompts
        UpgradePrompt::create([
            'name' => 'Advanced Analytics Prompt',
            'type' => 'feature_gate',
            'trigger_condition' => [
                'feature_access_denied' => [
                    'feature' => 'advanced_analytics'
                ]
            ],
            'content' => [
                'title' => 'Unlock Advanced Analytics',
                'message' => 'Get deeper insights with advanced analytics and custom dashboards.',
                'cta_text' => 'Try Premium',
                'benefits' => [
                    'Real-time analytics',
                    'Custom dashboards',
                    'Data export'
                ]
            ],
            'targeting_rules' => [
                'plan_slugs' => ['free', 'basic']
            ],
            'placement' => 'inline',
            'priority' => 75,
            'ab_test_config' => [
                'test_name' => 'analytics_prompt_test',
                'variants' => [
                    'control' => [],
                    'variant_a' => [
                        'title' => 'Supercharge Your Analytics',
                        'cta_text' => 'Start Free Trial'
                    ]
                ]
            ]
        ]);

        // Trial ending prompts
        UpgradePrompt::create([
            'name' => 'Trial Ending Soon',
            'type' => 'trial_ending',
            'trigger_condition' => [
                'time_since_signup' => [
                    'days' => 10
                ]
            ],
            'content' => [
                'title' => 'Your trial ends in 4 days',
                'message' => 'Continue enjoying premium features by upgrading your plan.',
                'cta_text' => 'Choose Plan',
                'urgency_text' => 'Don\'t lose access to your premium features!'
            ],
            'targeting_rules' => [
                'trial_days_remaining' => [
                    'min' => 1,
                    'max' => 5
                ]
            ],
            'placement' => 'modal',
            'priority' => 120,
            'max_displays_per_user' => 1,
            'cooldown_hours' => 12,
        ]);

        // API access prompt
        UpgradePrompt::create([
            'name' => 'API Access Needed',
            'type' => 'feature_gate',
            'trigger_condition' => [
                'specific_action' => [
                    'action' => 'api_access_attempt'
                ]
            ],
            'content' => [
                'title' => 'API Access Required',
                'message' => 'Integrate your favorite tools with our powerful API.',
                'cta_text' => 'Enable API Access',
                'benefits' => [
                    'RESTful API',
                    'Webhooks',
                    'Rate limits up to 10k/month'
                ]
            ],
            'targeting_rules' => [
                'plan_slugs' => ['free']
            ],
            'placement' => 'modal',
            'priority' => 85,
        ]);

        // Create A/B test variants for analytics prompt
        ABTestVariant::create([
            'test_name' => 'analytics_prompt_test',
            'variant_name' => 'control',
            'configuration' => [],
            'traffic_percentage' => 50,
            'is_active' => true,
            'start_date' => now(),
            'success_metrics' => ['conversion_rate', 'click_through_rate'],
        ]);

        ABTestVariant::create([
            'test_name' => 'analytics_prompt_test',
            'variant_name' => 'variant_a',
            'configuration' => [
                'title' => 'Supercharge Your Analytics',
                'cta_text' => 'Start Free Trial',
                'show_trial_badge' => true
            ],
            'traffic_percentage' => 50,
            'is_active' => true,
            'start_date' => now(),
            'success_metrics' => ['conversion_rate', 'click_through_rate'],
        ]);
    }
}