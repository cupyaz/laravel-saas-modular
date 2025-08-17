<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\UpgradePrompt;
use App\Models\UpgradePromptDisplay;
use App\Models\UpgradeConversion;
use App\Models\ABTestVariant;
use App\Models\ABTestAssignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class UpgradePromptService
{
    private ABTestService $abTestService;

    public function __construct(ABTestService $abTestService)
    {
        $this->abTestService = $abTestService;
    }

    /**
     * Get applicable upgrade prompts for a tenant in a specific context.
     */
    public function getPromptsForTenant(
        Tenant $tenant, 
        array $context = [], 
        string $placement = null
    ): Collection {
        $query = UpgradePrompt::active()->byPriority();

        if ($placement) {
            $query->forPlacement($placement);
        }

        $prompts = $query->get();
        $applicablePrompts = collect();

        foreach ($prompts as $prompt) {
            if ($prompt->shouldShowToTenant($tenant, $context)) {
                $variant = $this->abTestService->getVariantForTenant($tenant, $prompt);
                
                $promptData = [
                    'prompt' => $prompt,
                    'variant' => $variant,
                    'content' => $prompt->getContentForVariant($variant),
                    'display_id' => null, // Will be set when displayed
                ];

                $applicablePrompts->push($promptData);
            }
        }

        return $applicablePrompts;
    }

    /**
     * Record that a prompt was displayed to a tenant.
     */
    public function recordPromptDisplay(
        Tenant $tenant,
        UpgradePrompt $prompt,
        string $variant = 'control',
        array $context = [],
        string $placementLocation = 'unknown'
    ): UpgradePromptDisplay {
        $display = UpgradePromptDisplay::create([
            'tenant_id' => $tenant->id,
            'upgrade_prompt_id' => $prompt->id,
            'variant' => $variant,
            'context' => $context,
            'placement_location' => $placementLocation,
        ]);

        Log::info('Upgrade prompt displayed', [
            'tenant_id' => $tenant->id,
            'prompt_id' => $prompt->id,
            'variant' => $variant,
            'placement' => $placementLocation,
        ]);

        return $display;
    }

    /**
     * Record an action taken on a prompt.
     */
    public function recordPromptAction(
        UpgradePromptDisplay $display,
        string $action
    ): bool {
        $validActions = ['dismissed', 'clicked', 'converted', 'ignored'];
        
        if (!in_array($action, $validActions)) {
            return false;
        }

        $updateData = ['action_taken' => $action];
        
        switch ($action) {
            case 'dismissed':
                $updateData['dismissed_at'] = now();
                break;
            case 'clicked':
                $updateData['clicked_at'] = now();
                break;
            case 'converted':
                $updateData['converted_at'] = now();
                break;
        }

        $updated = $display->update($updateData);

        Log::info('Upgrade prompt action recorded', [
            'display_id' => $display->id,
            'action' => $action,
            'tenant_id' => $display->tenant_id,
        ]);

        return $updated;
    }

    /**
     * Record a successful conversion from a prompt.
     */
    public function recordConversion(
        UpgradePromptDisplay $display,
        int $subscriptionId,
        int $fromPlanId,
        int $toPlanId,
        float $conversionValue,
        array $conversionData = []
    ): UpgradeConversion {
        // Mark the display as converted
        $this->recordPromptAction($display, 'converted');

        // Create conversion record
        $conversion = UpgradeConversion::create([
            'tenant_id' => $display->tenant_id,
            'upgrade_prompt_display_id' => $display->id,
            'subscription_id' => $subscriptionId,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $toPlanId,
            'conversion_value' => $conversionValue,
            'conversion_data' => $conversionData,
        ]);

        Log::info('Upgrade conversion recorded', [
            'display_id' => $display->id,
            'conversion_id' => $conversion->id,
            'tenant_id' => $display->tenant_id,
            'conversion_value' => $conversionValue,
        ]);

        return $conversion;
    }

    /**
     * Get personalized upgrade recommendations for a tenant.
     */
    public function getPersonalizedRecommendations(Tenant $tenant): array
    {
        $usageTracker = app(UsageTracker::class);
        $currentPlan = $tenant->currentPlan();
        
        if (!$currentPlan) {
            return [];
        }

        $usageSummary = $usageTracker->getUsageSummary($tenant->id);
        $recommendations = [];

        // Analyze usage patterns to generate recommendations
        foreach ($usageSummary as $key => $usage) {
            if ($usage['percentage_used'] >= 80) {
                $recommendations[] = [
                    'type' => 'usage_limit',
                    'feature' => $usage['feature'],
                    'metric' => $usage['metric'],
                    'current_usage' => $usage['total_usage'],
                    'limit' => $usage['limit_value'],
                    'percentage_used' => $usage['percentage_used'],
                    'recommendation' => $this->generateUsageRecommendation($usage),
                ];
            }
        }

        // Add feature-based recommendations
        $featureRecommendations = $this->generateFeatureRecommendations($tenant);
        $recommendations = array_merge($recommendations, $featureRecommendations);

        // Add plan upgrade recommendations
        $planRecommendations = $this->generatePlanRecommendations($tenant);
        $recommendations = array_merge($recommendations, $planRecommendations);

        return $recommendations;
    }

    /**
     * Generate usage-based recommendation message.
     */
    private function generateUsageRecommendation(array $usage): string
    {
        $feature = ucwords(str_replace('_', ' ', $usage['feature']));
        $percentage = round($usage['percentage_used']);

        if ($percentage >= 100) {
            return "You've reached your {$feature} limit. Upgrade to continue using this feature.";
        } elseif ($percentage >= 90) {
            return "You're at {$percentage}% of your {$feature} limit. Consider upgrading to avoid interruptions.";
        } else {
            return "You're at {$percentage}% of your {$feature} limit. Upgrade for unlimited access.";
        }
    }

    /**
     * Generate feature-based recommendations.
     */
    private function generateFeatureRecommendations(Tenant $tenant): array
    {
        $currentPlan = $tenant->currentPlan();
        $recommendations = [];

        // Get available premium features not in current plan
        $premiumFeatures = [
            'advanced_analytics' => 'Get detailed insights into your data',
            'custom_branding' => 'Customize the platform with your brand',
            'priority_support' => 'Get faster response times for support',
            'api_access' => 'Integrate with your existing tools',
            'white_labeling' => 'Remove our branding completely',
        ];

        foreach ($premiumFeatures as $feature => $description) {
            if (!$currentPlan->hasFeature($feature)) {
                $recommendations[] = [
                    'type' => 'feature_access',
                    'feature' => $feature,
                    'description' => $description,
                    'recommendation' => "Unlock {$description} with a premium plan.",
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Generate plan upgrade recommendations.
     */
    private function generatePlanRecommendations(Tenant $tenant): array
    {
        $currentPlan = $tenant->currentPlan();
        $recommendations = [];

        // Find next tier plans
        $plans = \App\Models\Plan::active()
            ->where('price', '>', $currentPlan->price)
            ->orderBy('price')
            ->take(2)
            ->get();

        foreach ($plans as $plan) {
            $benefits = $this->calculatePlanBenefits($currentPlan, $plan);
            
            $recommendations[] = [
                'type' => 'plan_upgrade',
                'plan' => $plan,
                'current_plan' => $currentPlan,
                'price_difference' => $plan->price - $currentPlan->price,
                'benefits' => $benefits,
                'recommendation' => $this->generatePlanUpgradeMessage($currentPlan, $plan, $benefits),
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate benefits of upgrading to a new plan.
     */
    private function calculatePlanBenefits($currentPlan, $newPlan): array
    {
        $benefits = [];

        // Compare features
        $newFeatures = array_diff($newPlan->features ?? [], $currentPlan->features ?? []);
        if (!empty($newFeatures)) {
            $benefits['new_features'] = $newFeatures;
        }

        // Compare limits
        $currentLimits = $currentPlan->limits ?? [];
        $newLimits = $newPlan->limits ?? [];

        foreach ($newLimits as $resource => $limit) {
            $currentLimit = $currentLimits[$resource] ?? 0;
            if ($limit > $currentLimit || $limit === -1) {
                $benefits['increased_limits'][$resource] = [
                    'current' => $currentLimit,
                    'new' => $limit,
                    'increase' => $limit === -1 ? 'unlimited' : $limit - $currentLimit,
                ];
            }
        }

        return $benefits;
    }

    /**
     * Generate plan upgrade recommendation message.
     */
    private function generatePlanUpgradeMessage($currentPlan, $newPlan, array $benefits): string
    {
        $priceDiff = $newPlan->price - $currentPlan->price;
        $message = "Upgrade to {$newPlan->name} for only $" . number_format($priceDiff, 2) . " more per month.";

        if (!empty($benefits['increased_limits'])) {
            $limitIncreases = collect($benefits['increased_limits'])
                ->map(function ($limit, $resource) {
                    $resourceName = ucwords(str_replace('_', ' ', $resource));
                    return $limit['increase'] === 'unlimited' ? 
                        "unlimited {$resourceName}" : 
                        "{$limit['increase']}x more {$resourceName}";
                })
                ->take(2)
                ->implode(', ');
            
            $message .= " Get {$limitIncreases} and more.";
        }

        return $message;
    }

    /**
     * Get analytics for upgrade prompts.
     */
    public function getAnalytics(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->subDays(30);
        $endDate = $filters['end_date'] ?? now();

        $displays = UpgradePromptDisplay::whereBetween('created_at', [$startDate, $endDate]);
        $conversions = UpgradeConversion::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_displays' => $displays->count(),
            'total_conversions' => $conversions->count(),
            'conversion_rate' => $displays->count() > 0 ? 
                ($conversions->count() / $displays->count()) * 100 : 0,
            'total_revenue' => $conversions->sum('conversion_value'),
            'by_prompt' => $this->getAnalyticsByPrompt($startDate, $endDate),
            'by_placement' => $this->getAnalyticsByPlacement($startDate, $endDate),
            'by_variant' => $this->getAnalyticsByVariant($startDate, $endDate),
        ];
    }

    /**
     * Get analytics broken down by prompt.
     */
    private function getAnalyticsByPrompt($startDate, $endDate): array
    {
        return UpgradePrompt::withCount([
            'displays' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            },
            'displays as conversions_count' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('action_taken', 'converted');
            }
        ])
        ->get()
        ->map(function ($prompt) {
            return [
                'prompt_id' => $prompt->id,
                'prompt_name' => $prompt->name,
                'displays' => $prompt->displays_count,
                'conversions' => $prompt->conversions_count,
                'conversion_rate' => $prompt->displays_count > 0 ? 
                    ($prompt->conversions_count / $prompt->displays_count) * 100 : 0,
            ];
        })
        ->toArray();
    }

    /**
     * Get analytics broken down by placement.
     */
    private function getAnalyticsByPlacement($startDate, $endDate): array
    {
        return UpgradePromptDisplay::selectRaw('
            placement_location,
            COUNT(*) as displays,
            SUM(CASE WHEN action_taken = "converted" THEN 1 ELSE 0 END) as conversions
        ')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->groupBy('placement_location')
        ->get()
        ->map(function ($item) {
            return [
                'placement' => $item->placement_location,
                'displays' => $item->displays,
                'conversions' => $item->conversions,
                'conversion_rate' => $item->displays > 0 ? 
                    ($item->conversions / $item->displays) * 100 : 0,
            ];
        })
        ->toArray();
    }

    /**
     * Get analytics broken down by A/B test variant.
     */
    private function getAnalyticsByVariant($startDate, $endDate): array
    {
        return UpgradePromptDisplay::selectRaw('
            variant,
            COUNT(*) as displays,
            SUM(CASE WHEN action_taken = "converted" THEN 1 ELSE 0 END) as conversions
        ')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->whereNotNull('variant')
        ->groupBy('variant')
        ->get()
        ->map(function ($item) {
            return [
                'variant' => $item->variant,
                'displays' => $item->displays,
                'conversions' => $item->conversions,
                'conversion_rate' => $item->displays > 0 ? 
                    ($item->conversions / $item->displays) * 100 : 0,
            ];
        })
        ->toArray();
    }
}