<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Models\Tenant;
use App\Models\Feature;
use App\Services\UsageTracker;
use Symfony\Component\HttpFoundation\Response;

class FeatureGate
{
    protected UsageTracker $usageTracker;

    public function __construct(UsageTracker $usageTracker)
    {
        $this->usageTracker = $usageTracker;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $featureSlug, ?string $requiredQuantity = '1'): Response
    {
        $tenant = $this->resolveTenant($request);
        
        if (!$tenant) {
            return $this->handleUnauthorized($request, 'No tenant context found');
        }

        $plan = $tenant->currentPlan();
        
        if (!$plan) {
            return $this->handleFeatureBlocked($request, $featureSlug, 'No active subscription');
        }

        // Check if feature is included in plan
        if (!$plan->includesFeature($featureSlug)) {
            return $this->handleFeatureBlocked($request, $featureSlug, 'Feature not included in your plan');
        }

        // Check usage limits
        $currentUsage = $this->usageTracker->getCurrentUsage($tenant->id, $featureSlug);
        $requiredAmount = (int) $requiredQuantity;
        $newUsage = $currentUsage + $requiredAmount;

        if (!$plan->allowsFeatureQuantity($featureSlug, $newUsage)) {
            $limit = $plan->getFeatureLimit($featureSlug);
            return $this->handleLimitExceeded($request, $featureSlug, $currentUsage, $limit);
        }

        // Check for soft limit warnings (80% of limit)
        $limit = $plan->getFeatureLimit($featureSlug);
        if ($limit && $limit > 0) {
            $usagePercentage = ($newUsage / $limit) * 100;
            
            if ($usagePercentage >= 80) {
                $this->addLimitWarningToResponse($request, $featureSlug, $currentUsage, $limit);
            }
        }

        // Track the usage after successful check
        $this->usageTracker->track($tenant->id, $featureSlug, $requiredAmount);

        // Add feature context to request
        $request->merge([
            'feature_context' => [
                'slug' => $featureSlug,
                'current_usage' => $currentUsage,
                'new_usage' => $newUsage,
                'limit' => $limit,
                'plan' => $plan->name,
            ]
        ]);

        return $next($request);
    }

    /**
     * Resolve tenant from request context.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Try to get tenant from previous middleware
        if ($request->has('tenant')) {
            return $request->get('tenant');
        }

        // Try to get from authenticated user
        $user = $request->user();
        if ($user && $user->currentTenant()) {
            return $user->currentTenant();
        }

        // Try to resolve from tenant context
        $tenantId = $request->header('X-Tenant-ID') ?? session('tenant_id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    /**
     * Handle unauthorized access.
     */
    protected function handleUnauthorized(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $message,
                'code' => 'UNAUTHORIZED'
            ], 401);
        }

        return redirect()->route('login')->with('error', $message);
    }

    /**
     * Handle feature blocked access.
     */
    protected function handleFeatureBlocked(Request $request, string $featureSlug, string $reason): Response
    {
        $feature = Feature::where('slug', $featureSlug)->first();
        $featureName = $feature ? $feature->name : $featureSlug;

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Feature Access Denied',
                'message' => "Access to '{$featureName}' is not available in your current plan",
                'reason' => $reason,
                'feature' => $featureSlug,
                'code' => 'FEATURE_NOT_AVAILABLE',
                'upgrade_required' => true,
                'upgrade_url' => route('billing.plans')
            ], 403);
        }

        return redirect()->route('billing.plans')
            ->with('error', "Access to '{$featureName}' requires a plan upgrade")
            ->with('feature_required', $featureSlug);
    }

    /**
     * Handle limit exceeded.
     */
    protected function handleLimitExceeded(Request $request, string $featureSlug, int $currentUsage, ?int $limit): Response
    {
        $feature = Feature::where('slug', $featureSlug)->first();
        $featureName = $feature ? $feature->name : $featureSlug;

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Usage Limit Exceeded',
                'message' => "You have reached your limit for '{$featureName}'",
                'feature' => $featureSlug,
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'code' => 'USAGE_LIMIT_EXCEEDED',
                'upgrade_required' => true,
                'upgrade_url' => route('billing.plans'),
                'reset_date' => $this->getNextResetDate()
            ], 429);
        }

        return redirect()->back()
            ->with('error', "You have reached your limit for '{$featureName}' ({$currentUsage}/{$limit})")
            ->with('feature_limit_exceeded', $featureSlug)
            ->with('usage_info', [
                'current' => $currentUsage,
                'limit' => $limit,
                'reset_date' => $this->getNextResetDate()
            ]);
    }

    /**
     * Add usage warning to response headers.
     */
    protected function addLimitWarningToResponse(Request $request, string $featureSlug, int $currentUsage, int $limit): void
    {
        $feature = Feature::where('slug', $featureSlug)->first();
        $featureName = $feature ? $feature->name : $featureSlug;
        $percentage = round(($currentUsage / $limit) * 100);

        // Add warning headers that can be picked up by frontend
        if (!headers_sent()) {
            header("X-Usage-Warning: {$featureName} usage is at {$percentage}%");
            header("X-Usage-Current: {$currentUsage}");
            header("X-Usage-Limit: {$limit}");
            header("X-Feature-Slug: {$featureSlug}");
        }

        // Add flash message for web requests
        if (!$request->expectsJson()) {
            session()->flash('usage_warning', [
                'feature' => $featureName,
                'percentage' => $percentage,
                'current' => $currentUsage,
                'limit' => $limit
            ]);
        }
    }

    /**
     * Get next reset date for usage limits.
     */
    protected function getNextResetDate(): string
    {
        // For monthly limits, reset on the 1st of next month
        return now()->addMonth()->startOfMonth()->toISOString();
    }

    /**
     * Static method to check feature access without middleware.
     */
    public static function checkFeatureAccess(Tenant $tenant, string $featureSlug, int $quantity = 1): array
    {
        $plan = $tenant->currentPlan();
        
        if (!$plan) {
            return [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'No active subscription found'
            ];
        }

        if (!$plan->includesFeature($featureSlug)) {
            return [
                'allowed' => false,
                'reason' => 'feature_not_included',
                'message' => 'Feature not included in current plan'
            ];
        }

        $usageTracker = app(UsageTracker::class);
        $currentUsage = $usageTracker->getCurrentUsage($tenant->id, $featureSlug);
        $newUsage = $currentUsage + $quantity;

        if (!$plan->allowsFeatureQuantity($featureSlug, $newUsage)) {
            $limit = $plan->getFeatureLimit($featureSlug);
            return [
                'allowed' => false,
                'reason' => 'limit_exceeded',
                'message' => 'Usage limit would be exceeded',
                'current_usage' => $currentUsage,
                'limit' => $limit
            ];
        }

        return [
            'allowed' => true,
            'current_usage' => $currentUsage,
            'new_usage' => $newUsage,
            'limit' => $plan->getFeatureLimit($featureSlug)
        ];
    }
}