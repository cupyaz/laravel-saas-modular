<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UsageTracker;
use App\Models\UsageSummary;
use App\Models\UsageAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    private UsageTracker $usageTracker;

    public function __construct(UsageTracker $usageTracker)
    {
        $this->usageTracker = $usageTracker;
    }

    /**
     * Get current usage summary for authenticated tenant
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $period = $request->get('period', 'monthly');
        
        $summary = $this->usageTracker->getUsageSummary($tenantId, $period);
        
        return response()->json([
            'period' => $period,
            'summary' => $summary,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Get usage meters data for dashboard widgets
     */
    public function meters(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $period = $request->get('period', 'monthly');
        
        $summaries = UsageSummary::where('tenant_id', $tenantId)
            ->currentPeriod($period)
            ->get();
        
        $meters = $summaries->map(function ($summary) {
            return [
                'feature' => $summary->feature,
                'metric' => $summary->metric,
                'current_usage' => $summary->total_usage,
                'limit' => $summary->limit_value,
                'percentage_used' => $summary->percentage_used,
                'remaining' => $summary->getRemainingUsage(),
                'is_unlimited' => $summary->limit_value == -1,
                'is_approaching_limit' => $summary->isApproachingLimit(),
                'is_limit_exceeded' => $summary->isLimitExceeded(),
                'status' => $this->getUsageStatus($summary),
                'display_name' => $this->getDisplayName($summary->feature, $summary->metric),
                'unit' => $this->getMetricUnit($summary->metric),
            ];
        });
        
        return response()->json([
            'meters' => $meters,
            'period' => $period,
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Get usage analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $period = $request->get('period', 'monthly');
        $periodsBack = (int) $request->get('periods_back', 6);
        
        $analytics = $this->usageTracker->getAnalytics($tenantId, $period, $periodsBack);
        
        return response()->json([
            'analytics' => $analytics,
            'period' => $period,
            'periods_back' => $periodsBack
        ]);
    }

    /**
     * Get current alerts for the tenant
     */
    public function alerts(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $alerts = UsageAlert::where('tenant_id', $tenantId)
            ->where('is_sent', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'feature' => $alert->feature,
                    'metric' => $alert->metric,
                    'alert_type' => $alert->alert_type,
                    'message' => $alert->getFormattedMessage(),
                    'current_usage' => $alert->current_usage,
                    'limit_value' => $alert->limit_value,
                    'threshold_percentage' => $alert->threshold_percentage,
                    'created_at' => $alert->created_at->toISOString(),
                    'severity' => $this->getAlertSeverity($alert->alert_type),
                ];
            });
        
        return response()->json([
            'alerts' => $alerts,
            'count' => $alerts->count()
        ]);
    }

    /**
     * Mark alerts as acknowledged
     */
    public function acknowledgeAlerts(Request $request): JsonResponse
    {
        $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'integer|exists:usage_alerts,id'
        ]);
        
        $tenantId = $request->user()->tenant_id;
        
        $updated = UsageAlert::where('tenant_id', $tenantId)
            ->whereIn('id', $request->alert_ids)
            ->update(['is_sent' => true, 'sent_at' => now()]);
        
        return response()->json([
            'acknowledged' => $updated,
            'message' => "Acknowledged {$updated} alerts"
        ]);
    }

    /**
     * Track a specific usage event (for API integrations)
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'feature' => 'required|string|max:255',
            'metric' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'event_type' => 'nullable|string|in:increment,decrement,reset',
            'context' => 'nullable|array'
        ]);
        
        $tenantId = $request->user()->tenant_id;
        
        $success = $this->usageTracker->track(
            $tenantId,
            $request->feature,
            $request->metric,
            $request->get('amount', 1.0),
            $request->get('event_type', 'increment'),
            $request->get('context', [])
        );
        
        if (!$success) {
            return response()->json([
                'error' => 'Failed to track usage'
            ], 500);
        }
        
        return response()->json([
            'tracked' => true,
            'current_usage' => $this->usageTracker->getCurrentUsage(
                $tenantId, 
                $request->feature, 
                $request->metric
            )
        ]);
    }

    /**
     * Check if an action can be performed
     */
    public function canPerform(Request $request): JsonResponse
    {
        $request->validate([
            'feature' => 'required|string|max:255',
            'metric' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0'
        ]);
        
        $tenantId = $request->user()->tenant_id;
        
        $canPerform = $this->usageTracker->canPerformAction(
            $tenantId,
            $request->feature,
            $request->metric,
            $request->get('amount', 1.0)
        );
        
        $currentUsage = $this->usageTracker->getCurrentUsage(
            $tenantId,
            $request->feature,
            $request->metric
        );
        
        return response()->json([
            'can_perform' => $canPerform,
            'current_usage' => $currentUsage,
            'feature' => $request->feature,
            'metric' => $request->metric
        ]);
    }

    /**
     * Get usage status based on percentage
     */
    private function getUsageStatus(UsageSummary $summary): string
    {
        if ($summary->limit_value == -1) {
            return 'unlimited';
        }
        
        if ($summary->isLimitExceeded()) {
            return 'exceeded';
        }
        
        if ($summary->isApproachingLimit()) {
            return 'warning';
        }
        
        return 'normal';
    }

    /**
     * Get display name for feature/metric combination
     */
    private function getDisplayName(string $feature, string $metric): string
    {
        $displayNames = [
            'reports.generated' => 'Reports Generated',
            'reports.exports' => 'Report Exports',
            'storage.usage_mb' => 'Storage Used',
            'api.requests' => 'API Requests',
            'users.active' => 'Active Users',
            'projects.count' => 'Projects',
            'templates.custom' => 'Custom Templates',
            'integrations.active' => 'Active Integrations',
        ];
        
        $key = $feature . '.' . $metric;
        return $displayNames[$key] ?? ucwords(str_replace(['_', '.'], ' ', $key));
    }

    /**
     * Get unit for a metric
     */
    private function getMetricUnit(string $metric): string
    {
        $units = [
            'count' => 'items',
            'generated' => 'reports',
            'exports' => 'exports',
            'usage_mb' => 'MB',
            'requests' => 'requests',
            'active' => 'users',
            'custom' => 'templates',
        ];
        
        return $units[$metric] ?? 'units';
    }

    /**
     * Get alert severity level
     */
    private function getAlertSeverity(string $alertType): string
    {
        return match ($alertType) {
            'limit_exceeded' => 'critical',
            'limit_reached' => 'high',
            'warning' => 'medium',
            default => 'low'
        };
    }
}