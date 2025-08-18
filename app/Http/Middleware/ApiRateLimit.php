<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * Rate limit configurations for different tiers.
     */
    protected array $rateLimits = [
        'free' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
            'requests_per_day' => 10000,
        ],
        'basic' => [
            'requests_per_minute' => 200,
            'requests_per_hour' => 5000,
            'requests_per_day' => 50000,
        ],
        'pro' => [
            'requests_per_minute' => 500,
            'requests_per_hour' => 15000,
            'requests_per_day' => 150000,
        ],
        'enterprise' => [
            'requests_per_minute' => 1000,
            'requests_per_hour' => 50000,
            'requests_per_day' => 500000,
        ],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $tier = null): Response
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request);
        $rateLimitTier = $tier ?? $this->determineRateLimitTier($user, $tenant);
        
        $identifier = $this->getRateLimitIdentifier($request, $user, $tenant);
        
        // Check rate limits
        $rateLimitResult = $this->checkRateLimits($identifier, $rateLimitTier);
        
        if (!$rateLimitResult['allowed']) {
            return $this->handleRateLimitExceeded($rateLimitResult);
        }
        
        $response = $next($request);
        
        // Add rate limit headers
        $this->addRateLimitHeaders($response, $rateLimitResult);
        
        // Track API usage for analytics
        $this->trackApiUsage($request, $user, $tenant, $rateLimitTier);
        
        return $response;
    }

    /**
     * Determine rate limit tier based on user/tenant plan.
     */
    protected function determineRateLimitTier($user, $tenant): string
    {
        // If no user, use anonymous limits (most restrictive)
        if (!$user) {
            return 'free';
        }
        
        // Check tenant plan
        if ($tenant && $tenant->currentPlan()) {
            $plan = $tenant->currentPlan();
            
            // Map plan names to rate limit tiers
            $planToTier = [
                'free' => 'free',
                'basic' => 'basic',
                'pro' => 'pro',
                'enterprise' => 'enterprise',
            ];
            
            return $planToTier[strtolower($plan->name)] ?? 'free';
        }
        
        // Default to free tier
        return 'free';
    }

    /**
     * Get unique identifier for rate limiting.
     */
    protected function getRateLimitIdentifier(Request $request, $user, $tenant): string
    {
        // For authenticated users with tenant, use tenant-based limiting
        if ($user && $tenant) {
            return "tenant:{$tenant->id}";
        }
        
        // For authenticated users without tenant, use user-based limiting
        if ($user) {
            return "user:{$user->id}";
        }
        
        // For anonymous requests, use IP-based limiting
        return "ip:" . $request->ip();
    }

    /**
     * Check all rate limits (minute, hour, day).
     */
    protected function checkRateLimits(string $identifier, string $tier): array
    {
        $limits = $this->rateLimits[$tier];
        $now = now();
        
        $checks = [
            'minute' => [
                'window' => 60,
                'limit' => $limits['requests_per_minute'],
                'key' => "{$identifier}:minute:" . $now->format('Y-m-d-H-i'),
            ],
            'hour' => [
                'window' => 3600,
                'limit' => $limits['requests_per_hour'],
                'key' => "{$identifier}:hour:" . $now->format('Y-m-d-H'),
            ],
            'day' => [
                'window' => 86400,
                'limit' => $limits['requests_per_day'],
                'key' => "{$identifier}:day:" . $now->format('Y-m-d'),
            ],
        ];
        
        $result = [
            'allowed' => true,
            'tier' => $tier,
            'identifier' => $identifier,
            'limits' => [],
        ];
        
        foreach ($checks as $period => $config) {
            $current = Cache::get($config['key'], 0);
            $remaining = max(0, $config['limit'] - $current);
            
            $result['limits'][$period] = [
                'limit' => $config['limit'],
                'current' => $current,
                'remaining' => $remaining,
                'reset_at' => $this->getResetTime($period),
            ];
            
            if ($current >= $config['limit']) {
                $result['allowed'] = false;
                $result['exceeded_limit'] = $period;
                $result['retry_after'] = $this->getRetryAfter($period);
                break;
            }
            
            // Increment counter
            Cache::put($config['key'], $current + 1, $config['window']);
        }
        
        return $result;
    }

    /**
     * Handle rate limit exceeded.
     */
    protected function handleRateLimitExceeded(array $rateLimitResult): JsonResponse
    {
        $exceededLimit = $rateLimitResult['exceeded_limit'];
        $retryAfter = $rateLimitResult['retry_after'];
        
        return response()->json([
            'error' => 'Rate Limit Exceeded',
            'message' => "You have exceeded the {$exceededLimit}ly rate limit for your {$rateLimitResult['tier']} tier",
            'tier' => $rateLimitResult['tier'],
            'exceeded_limit' => $exceededLimit,
            'retry_after' => $retryAfter,
            'limits' => $rateLimitResult['limits'],
            'code' => 'RATE_LIMIT_EXCEEDED',
            'upgrade_info' => $this->getUpgradeInfo($rateLimitResult['tier']),
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $rateLimitResult['limits'][$exceededLimit]['limit'],
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => $rateLimitResult['limits'][$exceededLimit]['reset_at'],
        ]);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addRateLimitHeaders(Response $response, array $rateLimitResult): void
    {
        if (!$response instanceof JsonResponse && $response->headers->get('Content-Type') !== 'application/json') {
            return;
        }
        
        // Use minute limits for headers (most common)
        $minuteLimits = $rateLimitResult['limits']['minute'];
        
        $response->headers->set('X-RateLimit-Limit', $minuteLimits['limit']);
        $response->headers->set('X-RateLimit-Remaining', $minuteLimits['remaining']);
        $response->headers->set('X-RateLimit-Reset', $minuteLimits['reset_at']);
        $response->headers->set('X-RateLimit-Tier', $rateLimitResult['tier']);
        
        // Add detailed limits for each period
        foreach ($rateLimitResult['limits'] as $period => $limits) {
            $response->headers->set("X-RateLimit-{$period}-Limit", $limits['limit']);
            $response->headers->set("X-RateLimit-{$period}-Remaining", $limits['remaining']);
            $response->headers->set("X-RateLimit-{$period}-Reset", $limits['reset_at']);
        }
    }

    /**
     * Track API usage for analytics.
     */
    protected function trackApiUsage(Request $request, $user, $tenant, string $tier): void
    {
        // This could be expanded to use a proper analytics service
        $usage = [
            'timestamp' => now(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $user?->id,
            'tenant_id' => $tenant?->id,
            'tier' => $tier,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        
        // Store in cache for short-term analytics
        $key = "api_usage:" . now()->format('Y-m-d-H');
        $currentUsage = Cache::get($key, []);
        $currentUsage[] = $usage;
        Cache::put($key, $currentUsage, 3600); // Store for 1 hour
        
        // Log for long-term analytics
        \Log::info('API Usage', $usage);
    }

    /**
     * Resolve tenant from request.
     */
    protected function resolveTenant(Request $request)
    {
        // Try to get tenant from previous middleware
        if ($request->has('tenant')) {
            return $request->get('tenant');
        }
        
        // Try to get from user's current tenant
        $user = $request->user();
        if ($user && method_exists($user, 'currentTenant')) {
            return $user->currentTenant();
        }
        
        return null;
    }

    /**
     * Get reset time for period.
     */
    protected function getResetTime(string $period): int
    {
        $now = now();
        
        return match ($period) {
            'minute' => $now->addMinute()->startOfMinute()->timestamp,
            'hour' => $now->addHour()->startOfHour()->timestamp,
            'day' => $now->addDay()->startOfDay()->timestamp,
            default => $now->addMinute()->timestamp,
        };
    }

    /**
     * Get retry after seconds for period.
     */
    protected function getRetryAfter(string $period): int
    {
        $now = now();
        
        return match ($period) {
            'minute' => $now->diffInSeconds($now->copy()->addMinute()->startOfMinute()),
            'hour' => $now->diffInSeconds($now->copy()->addHour()->startOfHour()),
            'day' => $now->diffInSeconds($now->copy()->addDay()->startOfDay()),
            default => 60,
        };
    }

    /**
     * Get upgrade information for current tier.
     */
    protected function getUpgradeInfo(string $currentTier): ?array
    {
        $tierOrder = ['free', 'basic', 'pro', 'enterprise'];
        $currentIndex = array_search($currentTier, $tierOrder);
        
        if ($currentIndex === false || $currentIndex >= count($tierOrder) - 1) {
            return null;
        }
        
        $nextTier = $tierOrder[$currentIndex + 1];
        $nextLimits = $this->rateLimits[$nextTier];
        
        return [
            'next_tier' => $nextTier,
            'next_tier_limits' => $nextLimits,
            'upgrade_url' => route('billing.plans'),
            'message' => "Upgrade to {$nextTier} tier for higher rate limits",
        ];
    }

    /**
     * Get rate limit configuration.
     */
    public static function getRateLimitConfig(): array
    {
        return (new static())->rateLimits;
    }
}