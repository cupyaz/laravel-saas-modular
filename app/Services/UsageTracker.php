<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Models\UsageRecord;
use App\Models\UsageSummary;
use App\Models\UsageAlert;
use App\Models\Plan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UsageTracker
{
    private const REDIS_PREFIX = 'usage:';
    private const ALERT_THRESHOLDS = [80, 100];

    /**
     * Track usage for a tenant feature
     */
    public function track(
        int $tenantId,
        string $feature,
        string $metric,
        float $amount = 1.0,
        string $eventType = 'increment',
        array $context = []
    ): bool {
        try {
            // Record the usage event
            $this->recordEvent($tenantId, $feature, $metric, $amount, $eventType, $context);

            // Update Redis counters for real-time tracking
            $this->updateRedisCounters($tenantId, $feature, $metric, $amount, $eventType);

            // Update database summaries
            $this->updateUsageSummary($tenantId, $feature, $metric);

            // Check for limit violations and alerts
            $this->checkLimitsAndAlerts($tenantId, $feature, $metric);

            return true;
        } catch (\Exception $e) {
            Log::error('Usage tracking failed', [
                'tenant_id' => $tenantId,
                'feature' => $feature,
                'metric' => $metric,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get current usage for a tenant feature from Redis
     */
    public function getCurrentUsage(int $tenantId, string $feature, string $metric, string $period = 'monthly'): float
    {
        $key = $this->getRedisKey($tenantId, $feature, $metric, $period);
        $usage = Redis::get($key);
        return $usage ? (float) $usage : 0.0;
    }

    /**
     * Get usage summary for a tenant
     */
    public function getUsageSummary(int $tenantId, string $period = 'monthly'): array
    {
        $periodDate = $this->getPeriodDate($period);
        
        return UsageSummary::where('tenant_id', $tenantId)
            ->where('period', $period)
            ->where('period_date', $periodDate)
            ->get()
            ->keyBy(fn($summary) => $summary->feature . '.' . $summary->metric)
            ->toArray();
    }

    /**
     * Check if tenant can perform action based on limits
     */
    public function canPerformAction(int $tenantId, string $feature, string $metric, float $amount = 1.0): bool
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant || !$tenant->subscription) {
            return false;
        }

        $plan = $tenant->subscription->plan;
        if (!$plan) {
            return false;
        }

        $limitKey = $feature . '.' . $metric;
        $limit = $plan->getLimit($limitKey);
        
        // If no limit specified, allow unlimited usage
        if ($limit === null || $limit === -1) {
            return true;
        }

        $currentUsage = $this->getCurrentUsage($tenantId, $feature, $metric);
        return ($currentUsage + $amount) <= $limit;
    }

    /**
     * Reset usage counters for a new period
     */
    public function resetUsageForPeriod(int $tenantId, string $period = 'monthly'): void
    {
        $pattern = $this->getRedisKey($tenantId, '*', '*', $period);
        $keys = Redis::keys($pattern);
        
        if (!empty($keys)) {
            Redis::del($keys);
        }

        Log::info('Usage counters reset', [
            'tenant_id' => $tenantId,
            'period' => $period,
            'keys_deleted' => count($keys)
        ]);
    }

    /**
     * Get analytics data for a tenant
     */
    public function getAnalytics(int $tenantId, string $period = 'monthly', int $periodsBack = 6): array
    {
        $analytics = [];
        $currentDate = now();

        for ($i = 0; $i < $periodsBack; $i++) {
            $periodDate = match ($period) {
                'daily' => $currentDate->copy()->subDays($i)->format('Y-m-d'),
                'weekly' => $currentDate->copy()->subWeeks($i)->startOfWeek()->format('Y-m-d'),
                'monthly' => $currentDate->copy()->subMonths($i)->format('Y-m-01'),
                'yearly' => $currentDate->copy()->subYears($i)->format('Y-01-01'),
                default => $currentDate->copy()->subMonths($i)->format('Y-m-01'),
            };

            $summaries = UsageSummary::where('tenant_id', $tenantId)
                ->where('period', $period)
                ->where('period_date', $periodDate)
                ->get();

            $analytics[] = [
                'period_date' => $periodDate,
                'summaries' => $summaries->toArray()
            ];
        }

        return array_reverse($analytics);
    }

    /**
     * Record a usage event
     */
    private function recordEvent(int $tenantId, string $feature, string $metric, float $amount, string $eventType, array $context): void
    {
        UsageEvent::create([
            'tenant_id' => $tenantId,
            'feature' => $feature,
            'metric' => $metric,
            'amount' => $amount,
            'event_type' => $eventType,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Update Redis counters
     */
    private function updateRedisCounters(int $tenantId, string $feature, string $metric, float $amount, string $eventType): void
    {
        $periods = ['daily', 'weekly', 'monthly', 'yearly'];
        
        foreach ($periods as $period) {
            $key = $this->getRedisKey($tenantId, $feature, $metric, $period);
            $expirationTime = $this->getRedisExpiration($period);
            
            if ($eventType === 'increment') {
                Redis::incrbyfloat($key, $amount);
            } elseif ($eventType === 'decrement') {
                Redis::incrbyfloat($key, -$amount);
            } elseif ($eventType === 'reset') {
                Redis::set($key, $amount);
            }
            
            Redis::expire($key, $expirationTime);
        }
    }

    /**
     * Update usage summary in database
     */
    private function updateUsageSummary(int $tenantId, string $feature, string $metric): void
    {
        $periods = ['monthly', 'yearly'];
        
        foreach ($periods as $period) {
            $periodDate = $this->getPeriodDate($period);
            $currentUsage = $this->getCurrentUsage($tenantId, $feature, $metric, $period);
            
            // Get the limit for this feature/metric
            $tenant = Tenant::find($tenantId);
            $limit = $tenant?->subscription?->plan?->getLimit($feature . '.' . $metric) ?? -1;
            
            $percentageUsed = ($limit > 0) ? min(($currentUsage / $limit) * 100, 100) : 0;
            $limitExceeded = ($limit > 0) && ($currentUsage > $limit);
            
            UsageSummary::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'feature' => $feature,
                    'metric' => $metric,
                    'period' => $period,
                    'period_date' => $periodDate,
                ],
                [
                    'total_usage' => $currentUsage,
                    'limit_value' => $limit,
                    'percentage_used' => $percentageUsed,
                    'limit_exceeded' => $limitExceeded,
                    'last_updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Check limits and generate alerts if necessary
     */
    private function checkLimitsAndAlerts(int $tenantId, string $feature, string $metric): void
    {
        $currentUsage = $this->getCurrentUsage($tenantId, $feature, $metric, 'monthly');
        $tenant = Tenant::find($tenantId);
        $limit = $tenant?->subscription?->plan?->getLimit($feature . '.' . $metric);
        
        if ($limit === null || $limit <= 0) {
            return; // No limits to check
        }
        
        $percentageUsed = ($currentUsage / $limit) * 100;
        
        foreach (self::ALERT_THRESHOLDS as $threshold) {
            if ($percentageUsed >= $threshold) {
                $this->createUsageAlert($tenantId, $feature, $metric, $threshold, $currentUsage, $limit);
            }
        }
    }

    /**
     * Create a usage alert
     */
    private function createUsageAlert(int $tenantId, string $feature, string $metric, float $threshold, float $currentUsage, float $limit): void
    {
        $alertType = match (true) {
            $threshold >= 100 => 'limit_exceeded',
            $threshold >= 100 => 'limit_reached',
            default => 'warning'
        };

        // Check if we already sent this alert recently
        $existingAlert = UsageAlert::where('tenant_id', $tenantId)
            ->where('feature', $feature)
            ->where('metric', $metric)
            ->where('alert_type', $alertType)
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existingAlert) {
            return; // Don't spam alerts
        }

        UsageAlert::create([
            'tenant_id' => $tenantId,
            'feature' => $feature,
            'metric' => $metric,
            'alert_type' => $alertType,
            'threshold_percentage' => $threshold,
            'current_usage' => $currentUsage,
            'limit_value' => $limit,
            'notification_data' => [
                'percentage_used' => ($currentUsage / $limit) * 100,
                'remaining' => max(0, $limit - $currentUsage),
            ]
        ]);
    }

    /**
     * Get Redis key for usage tracking
     */
    private function getRedisKey(int $tenantId, string $feature, string $metric, string $period): string
    {
        $periodSuffix = $this->getPeriodDate($period);
        return self::REDIS_PREFIX . "{$tenantId}:{$feature}:{$metric}:{$period}:{$periodSuffix}";
    }

    /**
     * Get period date for Redis key and database queries
     */
    private function getPeriodDate(string $period): string
    {
        return match ($period) {
            'daily' => now()->format('Y-m-d'),
            'weekly' => now()->startOfWeek()->format('Y-m-d'),
            'monthly' => now()->format('Y-m-01'),
            'yearly' => now()->format('Y-01-01'),
            default => now()->format('Y-m-01'),
        };
    }

    /**
     * Get Redis expiration time in seconds
     */
    private function getRedisExpiration(string $period): int
    {
        return match ($period) {
            'daily' => 86400 * 2, // 2 days
            'weekly' => 86400 * 14, // 2 weeks
            'monthly' => 86400 * 62, // 2 months
            'yearly' => 86400 * 730, // 2 years
            default => 86400 * 62,
        };
    }
}