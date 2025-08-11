<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FeatureController extends Controller
{
    protected FeatureAccessService $featureAccessService;

    public function __construct(FeatureAccessService $featureAccessService)
    {
        $this->featureAccessService = $featureAccessService;
    }

    /**
     * Get all features with access information for the current tenant.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
                'features' => [],
            ], 404);
        }

        $features = $this->featureAccessService->getTenantFeatures($tenant);
        $isFreeTier = $this->featureAccessService->isFreeTier($tenant);
        $usageSummary = $this->featureAccessService->getUsageSummary($tenant);

        return response()->json([
            'features' => $features,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'current_plan' => $tenant->currentPlan()?->name,
                'is_free_tier' => $isFreeTier,
            ],
            'usage_summary' => $usageSummary,
        ]);
    }

    /**
     * Check access to a specific feature.
     */
    public function checkAccess(Request $request, string $feature): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
                'has_access' => false,
            ], 404);
        }

        $hasAccess = $this->featureAccessService->hasFeatureAccess($tenant, $feature);
        $featureInfo = $this->featureAccessService->getFeature($feature);
        
        $response = [
            'feature' => $feature,
            'has_access' => $hasAccess,
            'feature_info' => $featureInfo,
        ];

        if (!$hasAccess) {
            $upgradeInfo = $this->featureAccessService->getUpgradeInfo($tenant, $feature);
            $response['upgrade_info'] = $upgradeInfo;
        }

        return response()->json($response);
    }

    /**
     * Check usage limits for a specific feature.
     */
    public function checkUsage(Request $request, string $feature): JsonResponse
    {
        $request->validate([
            'limit_type' => 'required|string',
        ]);

        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
            ], 404);
        }

        $limitType = $request->limit_type;
        $usageInfo = $this->featureAccessService->checkUsageLimit($tenant, $feature, $limitType);
        $featureInfo = $this->featureAccessService->getFeature($feature);

        $response = [
            'feature' => $feature,
            'limit_type' => $limitType,
            'usage_info' => $usageInfo,
            'feature_info' => $featureInfo,
        ];

        if (!$usageInfo['allowed']) {
            $upgradeInfo = $this->featureAccessService->getUpgradeInfo($tenant, $feature);
            $response['upgrade_info'] = $upgradeInfo;
        }

        return response()->json($response);
    }

    /**
     * Get usage summary for the current tenant.
     */
    public function usageSummary(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
                'usage_summary' => [],
            ], 404);
        }

        $usageSummary = $this->featureAccessService->getUsageSummary($tenant);
        $isFreeTier = $this->featureAccessService->isFreeTier($tenant);
        
        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'current_plan' => $tenant->currentPlan()?->name,
                'is_free_tier' => $isFreeTier,
            ],
            'usage_summary' => $usageSummary,
            'approaching_limits' => array_filter($usageSummary, fn($item) => $item['is_approaching_limit']),
        ]);
    }

    /**
     * Increment usage for a specific feature (for internal use).
     */
    public function incrementUsage(Request $request): JsonResponse
    {
        $request->validate([
            'feature' => 'required|string',
            'limit_type' => 'required|string',
            'amount' => 'integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
            ], 404);
        }

        $feature = $request->feature;
        $limitType = $request->limit_type;
        $amount = $request->input('amount', 1);

        // Check if tenant has access to the feature
        if (!$this->featureAccessService->hasFeatureAccess($tenant, $feature)) {
            return response()->json([
                'message' => 'Feature access denied',
                'feature' => $feature,
            ], 403);
        }

        // Check current usage before incrementing
        $usageInfo = $this->featureAccessService->checkUsageLimit($tenant, $feature, $limitType);
        
        if (!$usageInfo['allowed']) {
            return response()->json([
                'message' => 'Usage limit would be exceeded',
                'feature' => $feature,
                'limit_type' => $limitType,
                'current_usage' => $usageInfo['current_usage'],
                'limit_value' => $usageInfo['limit_value'],
                'upgrade_info' => $this->featureAccessService->getUpgradeInfo($tenant, $feature),
            ], 429);
        }

        // Check if increment would exceed limit
        if ($usageInfo['limit_value'] !== -1 && 
            ($usageInfo['current_usage'] + $amount) > $usageInfo['limit_value']) {
            return response()->json([
                'message' => 'Usage limit would be exceeded',
                'feature' => $feature,
                'limit_type' => $limitType,
                'current_usage' => $usageInfo['current_usage'],
                'requested_amount' => $amount,
                'limit_value' => $usageInfo['limit_value'],
                'upgrade_info' => $this->featureAccessService->getUpgradeInfo($tenant, $feature),
            ], 429);
        }

        // Increment usage
        $this->featureAccessService->incrementUsage($tenant, $feature, $limitType, $amount);

        // Get updated usage info
        $updatedUsageInfo = $this->featureAccessService->checkUsageLimit($tenant, $feature, $limitType);

        return response()->json([
            'message' => 'Usage incremented successfully',
            'feature' => $feature,
            'limit_type' => $limitType,
            'amount_added' => $amount,
            'usage_info' => $updatedUsageInfo,
        ]);
    }

    /**
     * Get upgrade recommendations based on current usage patterns.
     */
    public function upgradeRecommendations(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
                'recommendations' => [],
            ], 404);
        }

        $usageSummary = $this->featureAccessService->getUsageSummary($tenant);
        $isFreeTier = $this->featureAccessService->isFreeTier($tenant);
        
        // Find features that are approaching limits
        $approachingLimits = array_filter($usageSummary, fn($item) => $item['is_approaching_limit']);
        
        // Get premium features that are not accessible
        $allFeatures = $this->featureAccessService->getAllFeatures();
        $restrictedFeatures = [];
        
        foreach ($allFeatures as $key => $feature) {
            if (!$this->featureAccessService->hasFeatureAccess($tenant, $key)) {
                $restrictedFeatures[$key] = $feature;
            }
        }

        $recommendations = [];
        
        // Add recommendations based on usage patterns
        if (!empty($approachingLimits)) {
            $recommendations[] = [
                'type' => 'usage_based',
                'title' => 'You\'re approaching your usage limits',
                'description' => 'Consider upgrading to avoid service interruptions',
                'approaching_limits' => $approachingLimits,
                'priority' => 'high',
            ];
        }

        // Add recommendations for premium features
        if (!empty($restrictedFeatures) && $isFreeTier) {
            $recommendations[] = [
                'type' => 'feature_based',
                'title' => 'Unlock premium features',
                'description' => 'Upgrade to access advanced functionality',
                'restricted_features' => array_slice($restrictedFeatures, 0, 3), // Show top 3
                'priority' => 'medium',
            ];
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'current_plan' => $tenant->currentPlan()?->name,
                'is_free_tier' => $isFreeTier,
            ],
            'recommendations' => $recommendations,
            'usage_summary' => $usageSummary,
        ]);
    }

    /**
     * Get all available features (public endpoint).
     */
    public function allFeatures(): JsonResponse
    {
        $features = $this->featureAccessService->getAllFeatures();
        
        // Remove internal implementation details
        $publicFeatures = array_map(function ($feature) {
            return [
                'name' => $feature['name'],
                'description' => $feature['description'],
                'free_tier' => $feature['free_tier'] ?? false,
                'required_plans' => $feature['required_plans'] ?? [],
                'has_limits' => isset($feature['limits']),
            ];
        }, $features);

        return response()->json([
            'features' => $publicFeatures,
        ]);
    }
}