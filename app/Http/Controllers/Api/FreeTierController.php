<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\UsageTracker;
use App\Http\Middleware\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FreeTierController extends Controller
{
    protected UsageTracker $usageTracker;

    public function __construct(UsageTracker $usageTracker)
    {
        $this->usageTracker = $usageTracker;
    }

    /**
     * Get current tenant's plan and feature access.
     */
    public function getCurrentPlan(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);
        $plan = $tenant->currentPlan();

        if (!$plan) {
            return response()->json([
                'error' => 'No active subscription found',
                'has_plan' => false,
                'free_trial_available' => true
            ], 404);
        }

        $features = $plan->getFreeTierFeatures();
        
        // Add current usage to each feature
        foreach ($features as &$feature) {
            $feature['current_usage'] = $this->usageTracker->getCurrentUsage($tenant->id, $feature['slug']);
            $feature['usage_percentage'] = $this->calculateUsagePercentage(
                $feature['current_usage'], 
                $feature['limit']
            );
        }

        return response()->json([
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->formatted_price,
                'billing_period' => $plan->billing_period_display,
                'is_free' => $plan->isFree(),
                'trial_days' => $plan->trial_days,
            ],
            'features' => $features,
            'has_plan' => true,
            'is_free_tier' => $plan->isFree()
        ]);
    }

    /**
     * Get all available features with free tier limitations.
     */
    public function getFeatures(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);
        $plan = $tenant->currentPlan();

        $features = Feature::active()
            ->with(['plans' => function ($query) use ($plan) {
                if ($plan) {
                    $query->where('plans.id', $plan->id);
                }
            }])
            ->get()
            ->groupBy('category');

        $result = [];
        foreach ($features as $category => $categoryFeatures) {
            $result[$category] = [
                'name' => Feature::getCategories()[$category] ?? ucfirst($category),
                'features' => $categoryFeatures->map(function ($feature) use ($plan, $tenant) {
                    $isIncluded = $plan && $plan->includesFeature($feature->slug);
                    $limit = $isIncluded ? $plan->getFeatureLimit($feature->slug) : 0;
                    $currentUsage = $isIncluded ? $this->usageTracker->getCurrentUsage($tenant->id, $feature->slug) : 0;

                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'slug' => $feature->slug,
                        'description' => $feature->description,
                        'is_premium' => $feature->is_premium,
                        'is_included' => $isIncluded,
                        'limit' => $limit,
                        'current_usage' => $currentUsage,
                        'usage_percentage' => $this->calculateUsagePercentage($currentUsage, $limit),
                        'is_unlimited' => $limit === null || $limit === -1,
                        'available_in_free' => !$feature->is_premium,
                    ];
                })->values()
            ];
        }

        return response()->json([
            'features_by_category' => $result,
            'plan_context' => [
                'current_plan' => $plan ? $plan->name : 'No Plan',
                'is_free_tier' => $plan ? $plan->isFree() : false,
                'upgrade_available' => true
            ]
        ]);
    }

    /**
     * Check specific feature access.
     */
    public function checkFeatureAccess(Request $request, string $featureSlug): JsonResponse
    {
        $request->validate([
            'quantity' => 'integer|min:1|max:1000'
        ]);

        $tenant = $this->getTenant($request);
        $quantity = $request->input('quantity', 1);

        $result = FeatureGate::checkFeatureAccess($tenant, $featureSlug, $quantity);

        return response()->json([
            'feature_slug' => $featureSlug,
            'requested_quantity' => $quantity,
            'access_result' => $result,
            'can_proceed' => $result['allowed']
        ]);
    }

    /**
     * Get usage statistics for tenant.
     */
    public function getUsageStats(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);
        $plan = $tenant->currentPlan();

        if (!$plan) {
            return response()->json([
                'error' => 'No active subscription found'
            ], 404);
        }

        $features = $plan->features()->wherePivot('is_included', true)->get();
        $stats = [];

        foreach ($features as $feature) {
            $currentUsage = $this->usageTracker->getCurrentUsage($tenant->id, $feature->slug);
            $limit = $feature->pivot->limit;
            
            $stats[] = [
                'feature' => [
                    'name' => $feature->name,
                    'slug' => $feature->slug,
                    'category' => $feature->category_display,
                ],
                'usage' => [
                    'current' => $currentUsage,
                    'limit' => $limit,
                    'percentage' => $this->calculateUsagePercentage($currentUsage, $limit),
                    'remaining' => $limit ? max(0, $limit - $currentUsage) : null,
                    'is_unlimited' => $limit === null || $limit === -1,
                ],
                'status' => $this->getUsageStatus($currentUsage, $limit),
            ];
        }

        // Sort by usage percentage descending
        usort($stats, function ($a, $b) {
            return $b['usage']['percentage'] <=> $a['usage']['percentage'];
        });

        return response()->json([
            'tenant_id' => $tenant->id,
            'plan' => $plan->name,
            'is_free_tier' => $plan->isFree(),
            'features_count' => count($stats),
            'usage_stats' => $stats,
            'reset_date' => now()->addMonth()->startOfMonth()->toISOString(),
        ]);
    }

    /**
     * Get feature comparison between plans.
     */
    public function getFeatureComparison(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);
        $currentPlan = $tenant->currentPlan();
        
        $plans = Plan::active()->with(['features' => function ($query) {
            $query->wherePivot('is_included', true);
        }])->get();

        $allFeatures = Feature::active()->get()->keyBy('slug');
        
        $comparison = $plans->map(function ($plan) use ($allFeatures, $currentPlan) {
            $planFeatures = [];
            
            foreach ($allFeatures as $feature) {
                $planFeature = $plan->features->where('slug', $feature->slug)->first();
                
                $planFeatures[$feature->slug] = [
                    'name' => $feature->name,
                    'category' => $feature->category,
                    'is_included' => $planFeature !== null,
                    'limit' => $planFeature ? $planFeature->pivot->limit : 0,
                    'is_unlimited' => $planFeature ? $planFeature->pivot->isUnlimited() : false,
                ];
            }

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->formatted_price,
                'billing_period' => $plan->billing_period_display,
                'is_current' => $currentPlan && $currentPlan->id === $plan->id,
                'is_free' => $plan->isFree(),
                'features' => $planFeatures,
                'upgrade_url' => $plan->isFree() ? null : route('billing.subscribe', $plan->id),
            ];
        });

        return response()->json([
            'plans' => $comparison,
            'current_plan_id' => $currentPlan ? $currentPlan->id : null,
            'feature_categories' => Feature::getCategories(),
        ]);
    }

    /**
     * Get upgrade recommendations based on usage.
     */
    public function getUpgradeRecommendations(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);
        $currentPlan = $tenant->currentPlan();

        if (!$currentPlan || !$currentPlan->isFree()) {
            return response()->json([
                'message' => 'Upgrade recommendations are only available for free tier users',
                'recommendations' => []
            ]);
        }

        $recommendations = [];
        $features = $currentPlan->features()->wherePivot('is_included', true)->get();

        foreach ($features as $feature) {
            $currentUsage = $this->usageTracker->getCurrentUsage($tenant->id, $feature->slug);
            $limit = $feature->pivot->limit;
            
            if ($limit && $currentUsage >= $limit * 0.8) { // 80% threshold
                $recommendations[] = [
                    'type' => 'limit_approaching',
                    'feature' => $feature->name,
                    'feature_slug' => $feature->slug,
                    'current_usage' => $currentUsage,
                    'limit' => $limit,
                    'percentage' => $this->calculateUsagePercentage($currentUsage, $limit),
                    'message' => "You're approaching your {$feature->name} limit",
                    'action' => 'Consider upgrading to get more quota',
                    'urgency' => $currentUsage >= $limit * 0.95 ? 'high' : 'medium'
                ];
            }
        }

        // Add general upgrade benefits
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'upgrade_benefits',
                'message' => 'Unlock premium features with a paid plan',
                'benefits' => [
                    'Unlimited or higher limits on all features',
                    'Priority customer support',
                    'Advanced analytics and reporting',
                    'Team collaboration tools',
                    'Advanced integrations'
                ],
                'urgency' => 'low'
            ];
        }

        $suggestedPlan = Plan::paid()->active()->orderBy('price')->first();

        return response()->json([
            'current_plan' => $currentPlan->name,
            'recommendations' => $recommendations,
            'suggested_plan' => $suggestedPlan ? [
                'id' => $suggestedPlan->id,
                'name' => $suggestedPlan->name,
                'price' => $suggestedPlan->formatted_price,
                'upgrade_url' => route('billing.subscribe', $suggestedPlan->id)
            ] : null,
            'upgrade_urgency' => $this->calculateUpgradeUrgency($recommendations)
        ]);
    }

    /**
     * Get tenant from request.
     */
    protected function getTenant(Request $request): Tenant
    {
        if ($request->has('tenant')) {
            return $request->get('tenant');
        }

        $user = $request->user();
        if ($user && $user->currentTenant()) {
            return $user->currentTenant();
        }

        $tenantId = $request->header('X-Tenant-ID') ?? session('tenant_id');
        if ($tenantId) {
            return Tenant::findOrFail($tenantId);
        }

        abort(401, 'No tenant context found');
    }

    /**
     * Calculate usage percentage.
     */
    protected function calculateUsagePercentage(int $current, ?int $limit): ?float
    {
        if (!$limit || $limit <= 0) {
            return null; // Unlimited
        }

        return round(($current / $limit) * 100, 1);
    }

    /**
     * Get usage status string.
     */
    protected function getUsageStatus(int $current, ?int $limit): string
    {
        if (!$limit || $limit <= 0) {
            return 'unlimited';
        }

        $percentage = ($current / $limit) * 100;

        if ($percentage >= 100) {
            return 'limit_reached';
        } elseif ($percentage >= 80) {
            return 'approaching_limit';
        } elseif ($percentage >= 50) {
            return 'moderate_usage';
        }

        return 'low_usage';
    }

    /**
     * Calculate overall upgrade urgency.
     */
    protected function calculateUpgradeUrgency(array $recommendations): string
    {
        $highUrgency = collect($recommendations)->where('urgency', 'high')->count();
        $mediumUrgency = collect($recommendations)->where('urgency', 'medium')->count();

        if ($highUrgency > 0) {
            return 'high';
        } elseif ($mediumUrgency > 0) {
            return 'medium';
        }

        return 'low';
    }
}