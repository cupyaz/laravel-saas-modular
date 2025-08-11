<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseInterface;

class CheckFeatureAccess
{
    protected FeatureAccessService $featureAccessService;

    public function __construct(FeatureAccessService $featureAccessService)
    {
        $this->featureAccessService = $featureAccessService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature, ?string $limit = null): ResponseInterface
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required',
                'feature' => $feature,
            ], 401);
        }

        $tenant = $user->tenant;
        
        if (!$tenant) {
            return response()->json([
                'message' => 'No active tenant found',
                'feature' => $feature,
            ], 403);
        }

        // Check feature access
        $hasAccess = $this->featureAccessService->hasFeatureAccess($tenant, $feature);
        
        if (!$hasAccess) {
            $upgradeInfo = $this->featureAccessService->getUpgradeInfo($tenant, $feature);
            
            return response()->json([
                'message' => 'Feature access restricted',
                'feature' => $feature,
                'current_plan' => $tenant->currentPlan()?->name,
                'upgrade_required' => true,
                'upgrade_info' => $upgradeInfo,
            ], 403);
        }

        // Check usage limits if specified
        if ($limit) {
            $limitCheck = $this->featureAccessService->checkUsageLimit($tenant, $feature, $limit);
            
            if (!$limitCheck['allowed']) {
                $upgradeInfo = $this->featureAccessService->getUpgradeInfo($tenant, $feature);
                
                return response()->json([
                    'message' => 'Usage limit exceeded',
                    'feature' => $feature,
                    'limit' => $limit,
                    'current_usage' => $limitCheck['current_usage'],
                    'limit_value' => $limitCheck['limit_value'],
                    'upgrade_required' => true,
                    'upgrade_info' => $upgradeInfo,
                ], 429); // Too Many Requests
            }
        }

        // Add feature info to request for use in controllers
        $request->merge([
            '_feature_access' => [
                'feature' => $feature,
                'tenant' => $tenant,
                'plan' => $tenant->currentPlan(),
                'has_access' => true,
            ]
        ]);

        return $next($request);
    }
}