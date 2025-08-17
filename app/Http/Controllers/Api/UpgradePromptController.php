<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UpgradePromptService;
use App\Services\ABTestService;
use App\Models\UpgradePromptDisplay;
use App\Models\UpgradePrompt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UpgradePromptController extends Controller
{
    private UpgradePromptService $upgradePromptService;
    private ABTestService $abTestService;

    public function __construct(
        UpgradePromptService $upgradePromptService,
        ABTestService $abTestService
    ) {
        $this->upgradePromptService = $upgradePromptService;
        $this->abTestService = $abTestService;
    }

    /**
     * Get available upgrade prompts for the authenticated tenant.
     */
    public function getPrompts(Request $request): JsonResponse
    {
        $request->validate([
            'placement' => 'nullable|string|in:modal,banner,inline,sidebar',
            'context' => 'nullable|array',
        ]);

        $tenant = $request->user()->tenant ?? $request->user();
        $placement = $request->get('placement');
        $context = $request->get('context', []);

        // Add request context
        $context['user_agent'] = $request->userAgent();
        $context['ip_address'] = $request->ip();
        $context['referer'] = $request->header('referer');

        $prompts = $this->upgradePromptService->getPromptsForTenant(
            $tenant,
            $context,
            $placement
        );

        // Transform prompts for API response
        $promptsData = $prompts->map(function ($promptData) use ($tenant, $context, $placement) {
            $prompt = $promptData['prompt'];
            $variant = $promptData['variant'];
            $content = $promptData['content'];

            // Record the display
            $display = $this->upgradePromptService->recordPromptDisplay(
                $tenant,
                $prompt,
                $variant,
                $context,
                $placement ?? 'api'
            );

            return [
                'id' => $prompt->id,
                'display_id' => $display->id,
                'type' => $prompt->type,
                'placement' => $prompt->placement,
                'variant' => $variant,
                'content' => $content,
                'priority' => $prompt->priority,
                'can_dismiss' => true,
                'metadata' => [
                    'max_displays' => $prompt->max_displays_per_user,
                    'cooldown_hours' => $prompt->cooldown_hours,
                ],
            ];
        });

        return response()->json([
            'prompts' => $promptsData,
            'count' => $promptsData->count(),
            'context' => $context,
        ]);
    }

    /**
     * Record an action taken on a prompt.
     */
    public function recordAction(Request $request): JsonResponse
    {
        $request->validate([
            'display_id' => 'required|integer|exists:upgrade_prompt_displays,id',
            'action' => 'required|string|in:dismissed,clicked,converted,ignored',
            'metadata' => 'nullable|array',
        ]);

        $tenant = $request->user()->tenant ?? $request->user();
        $display = UpgradePromptDisplay::where('id', $request->display_id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $success = $this->upgradePromptService->recordPromptAction(
            $display,
            $request->action
        );

        if (!$success) {
            return response()->json([
                'error' => 'Failed to record action'
            ], 500);
        }

        return response()->json([
            'recorded' => true,
            'action' => $request->action,
            'display_id' => $display->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get personalized upgrade recommendations.
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant ?? $request->user();
        
        $recommendations = $this->upgradePromptService->getPersonalizedRecommendations($tenant);

        return response()->json([
            'recommendations' => $recommendations,
            'count' => count($recommendations),
            'tenant_id' => $tenant->id,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Track a conversion from an upgrade prompt.
     */
    public function trackConversion(Request $request): JsonResponse
    {
        $request->validate([
            'display_id' => 'required|integer|exists:upgrade_prompt_displays,id',
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'from_plan_id' => 'required|integer|exists:plans,id',
            'to_plan_id' => 'required|integer|exists:plans,id',
            'conversion_value' => 'required|numeric|min:0',
            'conversion_data' => 'nullable|array',
        ]);

        $tenant = $request->user()->tenant ?? $request->user();
        $display = UpgradePromptDisplay::where('id', $request->display_id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $conversion = $this->upgradePromptService->recordConversion(
            $display,
            $request->subscription_id,
            $request->from_plan_id,
            $request->to_plan_id,
            $request->conversion_value,
            $request->get('conversion_data', [])
        );

        return response()->json([
            'conversion_recorded' => true,
            'conversion_id' => $conversion->id,
            'display_id' => $display->id,
            'conversion_value' => $conversion->conversion_value,
            'timestamp' => $conversion->created_at->toISOString(),
        ]);
    }

    /**
     * Get upgrade prompt analytics (admin only).
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        // TODO: Add admin middleware
        
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'prompt_id' => 'nullable|integer|exists:upgrade_prompts,id',
            'placement' => 'nullable|string',
        ]);

        $filters = $request->only(['start_date', 'end_date', 'prompt_id', 'placement']);
        
        $analytics = $this->upgradePromptService->getAnalytics($filters);

        return response()->json([
            'analytics' => $analytics,
            'filters' => $filters,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get A/B test results (admin only).
     */
    public function getABTestResults(Request $request): JsonResponse
    {
        // TODO: Add admin middleware
        
        $request->validate([
            'test_name' => 'nullable|string',
        ]);

        if ($request->test_name) {
            $results = $this->abTestService->getTestResults($request->test_name);
        } else {
            $results = $this->abTestService->getActiveTests();
        }

        return response()->json([
            'ab_test_results' => $results,
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Create a new A/B test (admin only).
     */
    public function createABTest(Request $request): JsonResponse
    {
        // TODO: Add admin middleware
        
        $request->validate([
            'test_name' => 'required|string|unique:ab_test_variants,test_name',
            'variants' => 'required|array|min:2',
            'variants.*.name' => 'required|string',
            'variants.*.traffic_percentage' => 'required|integer|min:1|max:100',
            'variants.*.configuration' => 'nullable|array',
            'variants.*.success_metrics' => 'nullable|array',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // Validate traffic percentages sum to 100
        $totalTraffic = array_sum(array_column($request->variants, 'traffic_percentage'));
        if ($totalTraffic !== 100) {
            return response()->json([
                'error' => 'Traffic percentages must sum to 100',
                'current_total' => $totalTraffic,
            ], 422);
        }

        try {
            $success = $this->abTestService->createABTest(
                $request->test_name,
                $request->variants,
                new \DateTime($request->start_date),
                $request->end_date ? new \DateTime($request->end_date) : null
            );

            if (!$success) {
                return response()->json([
                    'error' => 'Failed to create A/B test'
                ], 500);
            }

            return response()->json([
                'test_created' => true,
                'test_name' => $request->test_name,
                'variants' => count($request->variants),
                'start_date' => $request->start_date,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * End an A/B test and get results (admin only).
     */
    public function endABTest(Request $request): JsonResponse
    {
        // TODO: Add admin middleware
        
        $request->validate([
            'test_name' => 'required|string|exists:ab_test_variants,test_name',
        ]);

        try {
            $results = $this->abTestService->endABTest($request->test_name);

            return response()->json([
                'test_ended' => true,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get tenant's current A/B test assignments.
     */
    public function getMyAssignments(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant ?? $request->user();
        
        $assignments = $this->abTestService->getTenantAssignments($tenant);

        return response()->json([
            'assignments' => $assignments,
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Dismiss all active prompts for the tenant.
     */
    public function dismissAll(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant ?? $request->user();
        
        $activeDisplays = UpgradePromptDisplay::where('tenant_id', $tenant->id)
            ->whereNull('action_taken')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $dismissedCount = 0;
        foreach ($activeDisplays as $display) {
            if ($this->upgradePromptService->recordPromptAction($display, 'dismissed')) {
                $dismissedCount++;
            }
        }

        return response()->json([
            'dismissed_all' => true,
            'count' => $dismissedCount,
            'tenant_id' => $tenant->id,
        ]);
    }
}