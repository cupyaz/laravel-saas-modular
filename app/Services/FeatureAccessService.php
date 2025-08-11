<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeatureAccessService
{
    /**
     * Feature definitions with their requirements
     */
    protected array $features = [
        // Core features available to all plans
        'basic_dashboard' => [
            'name' => 'Basic Dashboard',
            'description' => 'Access to basic dashboard functionality',
            'free_tier' => true,
            'required_plans' => [],
        ],
        'profile_management' => [
            'name' => 'Profile Management',
            'description' => 'Manage user profile and settings',
            'free_tier' => true,
            'required_plans' => [],
        ],
        
        // Freemium features with limits
        'basic_reports' => [
            'name' => 'Basic Reports',
            'description' => 'Generate basic reports',
            'free_tier' => true,
            'required_plans' => [],
            'limits' => [
                'free' => ['reports_per_month' => 3],
                'basic' => ['reports_per_month' => 10],
                'premium' => ['reports_per_month' => -1], // unlimited
            ],
        ],
        'file_storage' => [
            'name' => 'File Storage',
            'description' => 'Upload and store files',
            'free_tier' => true,
            'required_plans' => [],
            'limits' => [
                'free' => ['storage_mb' => 100],
                'basic' => ['storage_mb' => 1000],
                'premium' => ['storage_mb' => 10000],
                'enterprise' => ['storage_mb' => -1], // unlimited
            ],
        ],
        'team_members' => [
            'name' => 'Team Members',
            'description' => 'Invite team members to your organization',
            'free_tier' => true,
            'required_plans' => [],
            'limits' => [
                'free' => ['max_members' => 1],
                'basic' => ['max_members' => 5],
                'premium' => ['max_members' => 20],
                'enterprise' => ['max_members' => -1], // unlimited
            ],
        ],
        'projects' => [
            'name' => 'Projects',
            'description' => 'Create and manage projects',
            'free_tier' => true,
            'required_plans' => [],
            'limits' => [
                'free' => ['max_projects' => 3],
                'basic' => ['max_projects' => 10],
                'premium' => ['max_projects' => 50],
                'enterprise' => ['max_projects' => -1], // unlimited
            ],
        ],
        
        // Premium features
        'advanced_analytics' => [
            'name' => 'Advanced Analytics',
            'description' => 'Advanced analytics and insights',
            'free_tier' => false,
            'required_plans' => ['premium', 'enterprise'],
        ],
        'custom_branding' => [
            'name' => 'Custom Branding',
            'description' => 'Customize the appearance with your branding',
            'free_tier' => false,
            'required_plans' => ['premium', 'enterprise'],
        ],
        'priority_support' => [
            'name' => 'Priority Support',
            'description' => 'Get priority customer support',
            'free_tier' => false,
            'required_plans' => ['premium', 'enterprise'],
        ],
        'api_access' => [
            'name' => 'API Access',
            'description' => 'Access to REST API endpoints',
            'free_tier' => false,
            'required_plans' => ['basic', 'premium', 'enterprise'],
            'limits' => [
                'basic' => ['api_calls_per_month' => 1000],
                'premium' => ['api_calls_per_month' => 10000],
                'enterprise' => ['api_calls_per_month' => -1], // unlimited
            ],
        ],
        'advanced_integrations' => [
            'name' => 'Advanced Integrations',
            'description' => 'Connect with external services and tools',
            'free_tier' => false,
            'required_plans' => ['premium', 'enterprise'],
        ],
        'white_labeling' => [
            'name' => 'White Labeling',
            'description' => 'Remove branding and customize interface',
            'free_tier' => false,
            'required_plans' => ['enterprise'],
        ],
        'sso_integration' => [
            'name' => 'SSO Integration',
            'description' => 'Single Sign-On integration',
            'free_tier' => false,
            'required_plans' => ['enterprise'],
        ],
        'audit_logs' => [
            'name' => 'Audit Logs',
            'description' => 'Comprehensive audit logging',
            'free_tier' => false,
            'required_plans' => ['enterprise'],
        ],
        'custom_fields' => [
            'name' => 'Custom Fields',
            'description' => 'Create custom fields and data structures',
            'free_tier' => false,
            'required_plans' => ['premium', 'enterprise'],
        ],
        'export_data' => [
            'name' => 'Data Export',
            'description' => 'Export your data in various formats',
            'free_tier' => false,
            'required_plans' => ['basic', 'premium', 'enterprise'],
        ],
    ];

    /**
     * Check if a tenant has access to a specific feature.
     */
    public function hasFeatureAccess(Tenant $tenant, string $featureKey): bool
    {
        $cacheKey = "feature_access:{$tenant->id}:{$featureKey}";
        
        return Cache::remember($cacheKey, 300, function () use ($tenant, $featureKey) {
            return $this->checkFeatureAccess($tenant, $featureKey);
        });
    }

    /**
     * Check feature access without caching.
     */
    protected function checkFeatureAccess(Tenant $tenant, string $featureKey): bool
    {
        $feature = $this->getFeature($featureKey);
        
        if (!$feature) {
            Log::warning("Unknown feature requested: {$featureKey}");
            return false;
        }

        // Always allow if feature is in free tier
        if ($feature['free_tier'] ?? false) {
            return true;
        }

        // Check if tenant has active subscription
        $subscription = $tenant->subscription();
        
        if (!$subscription || !$subscription->isActive()) {
            return false;
        }

        $plan = $subscription->plan;
        
        if (!$plan) {
            return false;
        }

        // Check if current plan has access to this feature
        $requiredPlans = $feature['required_plans'] ?? [];
        
        if (empty($requiredPlans)) {
            return true;
        }

        return in_array($plan->slug, $requiredPlans) || $plan->hasFeature($featureKey);
    }

    /**
     * Check usage limits for a feature.
     */
    public function checkUsageLimit(Tenant $tenant, string $featureKey, string $limitType): array
    {
        $feature = $this->getFeature($featureKey);
        
        if (!$feature || !isset($feature['limits'])) {
            return ['allowed' => true];
        }

        $planSlug = $this->getTenantPlanSlug($tenant);
        $limits = $feature['limits'][$planSlug] ?? [];
        
        if (!isset($limits[$limitType])) {
            return ['allowed' => true];
        }

        $limitValue = $limits[$limitType];
        
        // -1 means unlimited
        if ($limitValue === -1) {
            return ['allowed' => true, 'limit_value' => -1];
        }

        $currentUsage = $this->getCurrentUsage($tenant, $featureKey, $limitType);
        $allowed = $currentUsage < $limitValue;

        return [
            'allowed' => $allowed,
            'current_usage' => $currentUsage,
            'limit_value' => $limitValue,
            'remaining' => max(0, $limitValue - $currentUsage),
        ];
    }

    /**
     * Get current usage for a specific limit.
     */
    protected function getCurrentUsage(Tenant $tenant, string $featureKey, string $limitType): int
    {
        // This would integrate with actual usage tracking
        // For now, return mock data based on cache or database
        $cacheKey = "usage:{$tenant->id}:{$featureKey}:{$limitType}";
        
        return Cache::get($cacheKey, 0);
    }

    /**
     * Increment usage for a feature.
     */
    public function incrementUsage(Tenant $tenant, string $featureKey, string $limitType, int $amount = 1): void
    {
        $cacheKey = "usage:{$tenant->id}:{$featureKey}:{$limitType}";
        $current = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $current + $amount, now()->endOfMonth());

        Log::info("Usage incremented", [
            'tenant_id' => $tenant->id,
            'feature' => $featureKey,
            'limit_type' => $limitType,
            'amount' => $amount,
            'new_total' => $current + $amount,
        ]);
    }

    /**
     * Get upgrade information for a feature.
     */
    public function getUpgradeInfo(Tenant $tenant, string $featureKey): array
    {
        $feature = $this->getFeature($featureKey);
        
        if (!$feature) {
            return [];
        }

        $currentPlan = $tenant->currentPlan();
        $requiredPlans = $feature['required_plans'] ?? [];
        
        // Find the cheapest plan that provides access to this feature
        $upgradePlans = Plan::active()
            ->where(function ($query) use ($requiredPlans, $featureKey) {
                $query->whereIn('slug', $requiredPlans)
                      ->orWhereJsonContains('features', $featureKey);
            })
            ->when($currentPlan, function ($query, $currentPlan) {
                return $query->where('price', '>', $currentPlan->price);
            })
            ->orderBy('price')
            ->limit(3)
            ->get();

        return [
            'feature' => $feature,
            'current_plan' => $currentPlan?->name,
            'upgrade_plans' => $upgradePlans->map(function ($plan) use ($currentPlan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'billing_period' => $plan->billing_period,
                    'savings_text' => $this->calculateSavings($currentPlan, $plan),
                ];
            }),
        ];
    }

    /**
     * Get all features available to a tenant.
     */
    public function getTenantFeatures(Tenant $tenant): array
    {
        $planSlug = $this->getTenantPlanSlug($tenant);
        $features = [];

        foreach ($this->features as $key => $feature) {
            $hasAccess = $this->hasFeatureAccess($tenant, $key);
            $limits = $feature['limits'][$planSlug] ?? null;
            
            $features[$key] = [
                'name' => $feature['name'],
                'description' => $feature['description'],
                'has_access' => $hasAccess,
                'limits' => $limits,
                'is_premium' => !($feature['free_tier'] ?? false),
            ];

            // Add usage information if there are limits
            if ($limits) {
                foreach ($limits as $limitType => $limitValue) {
                    if ($hasAccess) {
                        $usageInfo = $this->checkUsageLimit($tenant, $key, $limitType);
                        $features[$key]['usage'][$limitType] = $usageInfo;
                    }
                }
            }
        }

        return $features;
    }

    /**
     * Get feature information.
     */
    public function getFeature(string $featureKey): ?array
    {
        return $this->features[$featureKey] ?? null;
    }

    /**
     * Get all available features.
     */
    public function getAllFeatures(): array
    {
        return $this->features;
    }

    /**
     * Get tenant's plan slug.
     */
    protected function getTenantPlanSlug(Tenant $tenant): string
    {
        $subscription = $tenant->subscription();
        
        if (!$subscription || !$subscription->isActive()) {
            return 'free';
        }

        return $subscription->plan->slug ?? 'free';
    }

    /**
     * Calculate savings text for plan comparison.
     */
    protected function calculateSavings(?Plan $currentPlan, Plan $newPlan): ?string
    {
        if (!$currentPlan) {
            return null;
        }

        $difference = $newPlan->price - $currentPlan->price;
        
        if ($difference <= 0) {
            return null;
        }

        return "+" . number_format($difference, 2) . " " . $newPlan->billing_period_display;
    }

    /**
     * Clear feature access cache for a tenant.
     */
    public function clearCache(Tenant $tenant): void
    {
        $pattern = "feature_access:{$tenant->id}:*";
        
        // This would depend on your cache implementation
        // For Redis: Cache::getStore()->getRedis()->del(Cache::getStore()->getRedis()->keys($pattern));
        // For now, we'll just log
        Log::info("Feature access cache cleared for tenant {$tenant->id}");
    }

    /**
     * Check if tenant is on free tier.
     */
    public function isFreeTier(Tenant $tenant): bool
    {
        $subscription = $tenant->subscription();
        
        return !$subscription || 
               !$subscription->isActive() || 
               $subscription->plan->isFree();
    }

    /**
     * Get feature usage summary for a tenant.
     */
    public function getUsageSummary(Tenant $tenant): array
    {
        $planSlug = $this->getTenantPlanSlug($tenant);
        $summary = [];

        foreach ($this->features as $key => $feature) {
            if (!isset($feature['limits'][$planSlug])) {
                continue;
            }

            $limits = $feature['limits'][$planSlug];
            
            foreach ($limits as $limitType => $limitValue) {
                if ($limitValue === -1) continue; // Skip unlimited
                
                $usageInfo = $this->checkUsageLimit($tenant, $key, $limitType);
                
                $summary[] = [
                    'feature' => $feature['name'],
                    'limit_type' => $limitType,
                    'current_usage' => $usageInfo['current_usage'],
                    'limit_value' => $limitValue,
                    'percentage_used' => $limitValue > 0 ? ($usageInfo['current_usage'] / $limitValue) * 100 : 0,
                    'is_approaching_limit' => $limitValue > 0 && ($usageInfo['current_usage'] / $limitValue) > 0.8,
                ];
            }
        }

        return $summary;
    }
}