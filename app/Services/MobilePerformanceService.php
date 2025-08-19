<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Http\Request as HttpRequest;
use Carbon\Carbon;

class MobilePerformanceService
{
    private const CACHE_PREFIX = 'mobile_perf:';
    private const DEFAULT_CACHE_TTL = 3600; // 1 hour
    private const CRITICAL_CACHE_TTL = 300; // 5 minutes
    private const EXTENDED_CACHE_TTL = 86400; // 24 hours

    /**
     * Optimize API response for mobile devices.
     */
    public function optimizeResponse(array $data, HttpRequest $request): array
    {
        $isMobile = $this->isMobileDevice($request);
        $connectionType = $this->getConnectionType($request);

        if (!$isMobile) {
            return $data;
        }

        $optimizedData = $data;

        // Apply mobile-specific optimizations
        $optimizedData = $this->compressImageUrls($optimizedData, $connectionType);
        $optimizedData = $this->reducePagination($optimizedData, $connectionType);
        $optimizedData = $this->simplifyMetadata($optimizedData);
        $optimizedData = $this->lazyLoadProperties($optimizedData);

        // Log performance metrics
        $this->logPerformanceMetrics($request, [
            'original_size' => strlen(json_encode($data)),
            'optimized_size' => strlen(json_encode($optimizedData)),
            'connection_type' => $connectionType,
            'optimization_applied' => true
        ]);

        return $optimizedData;
    }

    /**
     * Implement intelligent caching for mobile devices.
     */
    public function getCachedData(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
    {
        $request = request();
        $isMobile = $this->isMobileDevice($request);
        $connectionType = $this->getConnectionType($request);

        // Adjust cache TTL based on device and connection
        if ($isMobile) {
            $ttl = $this->getMobileCacheTTL($connectionType, $ttl);
            $key = $this->buildMobileCacheKey($key, $request);
        }

        $cacheKey = self::CACHE_PREFIX . $key;

        // Try to get from cache first
        $cached = Cache::tags(array_merge(['mobile_performance'], $tags))->get($cacheKey);
        
        if ($cached !== null) {
            $this->recordCacheHit($cacheKey, 'hit');
            return $cached;
        }

        // Cache miss - generate data
        $startTime = microtime(true);
        $data = $callback();
        $generationTime = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Cache the data with appropriate TTL
        Cache::tags(array_merge(['mobile_performance'], $tags))->put($cacheKey, $data, $ttl);

        // Log cache performance
        $this->recordCacheHit($cacheKey, 'miss', $generationTime);

        return $data;
    }

    /**
     * Preload critical data for mobile users.
     */
    public function preloadCriticalData(HttpRequest $request, array $preloadConfig = []): array
    {
        if (!$this->isMobileDevice($request)) {
            return [];
        }

        $userId = $request->user()?->id;
        $tenantId = $request->user()?->tenant_id;
        
        $preloadData = [];
        $connectionType = $this->getConnectionType($request);

        // Determine what to preload based on connection type
        $criticalResources = $this->getCriticalResources($connectionType, $preloadConfig);

        foreach ($criticalResources as $resource => $config) {
            try {
                $cacheKey = "preload:{$resource}:user:{$userId}:tenant:{$tenantId}";
                
                $preloadData[$resource] = $this->getCachedData(
                    $cacheKey,
                    fn() => $this->generatePreloadData($resource, $request, $config),
                    $config['ttl'] ?? self::CRITICAL_CACHE_TTL,
                    ['preload', $resource]
                );

            } catch (\Exception $e) {
                Log::warning("Failed to preload {$resource} for mobile user", [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $preloadData;
    }

    /**
     * Optimize images for mobile devices.
     */
    public function optimizeImageUrls(array $data, string $connectionType = 'unknown'): array
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->optimizeImageUrls($value, $connectionType);
            } elseif (is_string($value) && $this->isImageUrl($value)) {
                $value = $this->generateOptimizedImageUrl($value, $connectionType);
            }
        }

        return $data;
    }

    /**
     * Implement lazy loading for non-critical data.
     */
    public function getLazyLoadData(string $resource, array $params, HttpRequest $request): array
    {
        $isMobile = $this->isMobileDevice($request);
        $connectionType = $this->getConnectionType($request);

        // Build cache key
        $cacheKey = "lazy_load:{$resource}:" . md5(serialize($params));
        
        return $this->getCachedData(
            $cacheKey,
            function() use ($resource, $params, $isMobile, $connectionType) {
                $data = $this->generateLazyLoadData($resource, $params);
                
                if ($isMobile) {
                    $data = $this->optimizeLazyLoadForMobile($data, $connectionType);
                }
                
                return $data;
            },
            $this->getLazyLoadCacheTTL($resource, $connectionType),
            ['lazy_load', $resource]
        );
    }

    /**
     * Monitor mobile performance metrics.
     */
    public function trackPerformanceMetrics(HttpRequest $request, array $metrics): void
    {
        if (!$this->isMobileDevice($request)) {
            return;
        }

        $performanceData = array_merge($metrics, [
            'timestamp' => now()->toISOString(),
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id,
            'device_info' => $this->getDeviceInfo($request),
            'connection_type' => $this->getConnectionType($request),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
        ]);

        // Store in cache for real-time dashboard
        $cacheKey = 'mobile_metrics:' . now()->format('Y-m-d-H');
        $existingMetrics = Cache::get($cacheKey, []);
        $existingMetrics[] = $performanceData;
        
        // Keep only last 100 entries per hour
        if (count($existingMetrics) > 100) {
            $existingMetrics = array_slice($existingMetrics, -100);
        }
        
        Cache::put($cacheKey, $existingMetrics, 3600); // 1 hour

        // Log for long-term analysis
        Log::channel('mobile_performance')->info('Mobile Performance Metrics', $performanceData);
    }

    /**
     * Get mobile performance dashboard data.
     */
    public function getPerformanceDashboard(int $hours = 24): array
    {
        $dashboard = [
            'overview' => $this->getPerformanceOverview($hours),
            'device_breakdown' => $this->getDeviceBreakdown($hours),
            'connection_analysis' => $this->getConnectionAnalysis($hours),
            'endpoint_performance' => $this->getEndpointPerformance($hours),
            'cache_performance' => $this->getCachePerformance($hours),
            'optimization_impact' => $this->getOptimizationImpact($hours),
            'real_time_metrics' => $this->getRealTimeMetrics(),
        ];

        return $dashboard;
    }

    /**
     * Optimize offline capabilities and PWA features.
     */
    public function getOfflineCapabilities(HttpRequest $request): array
    {
        if (!$this->isMobileDevice($request)) {
            return [];
        }

        return [
            'cache_strategy' => $this->generateCacheStrategy($request),
            'offline_pages' => $this->getOfflinePages($request),
            'sync_config' => $this->getSyncConfiguration($request),
            'background_sync' => $this->getBackgroundSyncTasks($request),
            'push_notifications' => $this->getPushNotificationConfig($request),
        ];
    }

    /**
     * Network retry logic with exponential backoff.
     */
    public function executeWithRetry(callable $callback, int $maxRetries = 3, int $baseDelay = 1000): mixed
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                // Exponential backoff: 1s, 2s, 4s
                $delay = $baseDelay * pow(2, $attempt - 1);
                usleep($delay * 1000); // Convert to microseconds
                
                Log::warning("Mobile API retry attempt {$attempt}", [
                    'error' => $e->getMessage(),
                    'delay_ms' => $delay
                ]);
            }
        }
    }

    /**
     * Database query optimization for mobile.
     */
    public function optimizeQueryForMobile(\Illuminate\Database\Eloquent\Builder $query, HttpRequest $request): \Illuminate\Database\Eloquent\Builder
    {
        if (!$this->isMobileDevice($request)) {
            return $query;
        }

        $connectionType = $this->getConnectionType($request);

        // Adjust pagination for mobile
        $mobileLimit = $this->getMobilePaginationLimit($connectionType);
        
        // Optimize select fields for mobile
        if (!$query->getQuery()->columns) {
            $query->select($this->getMobileOptimizedColumns($query->getModel()));
        }

        // Add mobile-specific ordering
        $query->orderBy('updated_at', 'desc');

        return $query;
    }

    /**
     * Check if the request is from a mobile device.
     */
    public function isMobileDevice(HttpRequest $request): bool
    {
        $userAgent = strtolower($request->userAgent());
        
        $mobileKeywords = [
            'mobile', 'android', 'iphone', 'ipad', 'ipod', 'blackberry', 
            'webos', 'opera mini', 'iemobile', 'phone'
        ];

        foreach ($mobileKeywords as $keyword) {
            if (strpos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        // Check for mobile indicators in headers
        return $request->hasHeader('X-Mobile-Device') ||
               $request->hasHeader('X-Requested-With') && 
               $request->header('X-Requested-With') === 'MobileApp';
    }

    /**
     * Detect connection type from request headers.
     */
    public function getConnectionType(HttpRequest $request): string
    {
        // Check for connection type headers
        $networkInfo = $request->header('Network-Information');
        if ($networkInfo) {
            return $networkInfo;
        }

        $saveData = $request->header('Save-Data');
        if ($saveData === 'on') {
            return 'slow-2g';
        }

        // Default assumption for mobile
        return $this->isMobileDevice($request) ? '4g' : 'wifi';
    }

    /**
     * Get device information from user agent.
     */
    public function getDeviceInfo(HttpRequest $request): array
    {
        $userAgent = $request->userAgent();
        
        return [
            'type' => $this->isMobileDevice($request) ? 'mobile' : 'desktop',
            'platform' => $this->detectPlatform($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'is_mobile' => $this->isMobileDevice($request),
            'user_agent' => $userAgent
        ];
    }

    // Private helper methods

    private function compressImageUrls(array $data, string $connectionType): array
    {
        return $this->optimizeImageUrls($data, $connectionType);
    }

    private function reducePagination(array $data, string $connectionType): array
    {
        if (isset($data['per_page'])) {
            $mobileLimit = match($connectionType) {
                'slow-2g', '2g' => 5,
                '3g' => 10,
                '4g' => 15,
                default => 20
            };
            
            $data['per_page'] = min($data['per_page'], $mobileLimit);
        }

        return $data;
    }

    private function simplifyMetadata(array $data): array
    {
        // Remove non-essential metadata for mobile
        $keysToRemove = ['debug_info', 'query_log', 'profiling_data'];
        
        foreach ($keysToRemove as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    private function lazyLoadProperties(array $data): array
    {
        // Mark properties for lazy loading
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as &$item) {
                if (is_array($item)) {
                    // Move heavy properties to lazy_load section
                    $heavyProperties = ['full_description', 'detailed_metadata', 'large_arrays'];
                    
                    foreach ($heavyProperties as $prop) {
                        if (isset($item[$prop])) {
                            $item['_lazy_load'][$prop] = true;
                            $item[$prop] = '[Lazy loaded - use endpoint to fetch]';
                        }
                    }
                }
            }
        }

        return $data;
    }

    private function getMobileCacheTTL(string $connectionType, ?int $defaultTTL): int
    {
        if ($defaultTTL !== null) {
            return $defaultTTL;
        }

        return match($connectionType) {
            'slow-2g', '2g' => self::EXTENDED_CACHE_TTL,
            '3g' => self::DEFAULT_CACHE_TTL * 2,
            '4g' => self::DEFAULT_CACHE_TTL,
            default => self::DEFAULT_CACHE_TTL
        };
    }

    private function buildMobileCacheKey(string $key, HttpRequest $request): string
    {
        $deviceInfo = $this->getDeviceInfo($request);
        $connectionType = $this->getConnectionType($request);
        
        return $key . ':mobile:' . md5($deviceInfo['platform'] . ':' . $connectionType);
    }

    private function recordCacheHit(string $key, string $type, float $generationTime = 0): void
    {
        $metricsKey = 'cache_metrics:' . now()->format('Y-m-d-H');
        $metrics = Cache::get($metricsKey, ['hits' => 0, 'misses' => 0, 'total_generation_time' => 0]);
        
        $metrics[$type === 'hit' ? 'hits' : 'misses']++;
        if ($type === 'miss') {
            $metrics['total_generation_time'] += $generationTime;
        }
        
        Cache::put($metricsKey, $metrics, 3600);
    }

    private function getCriticalResources(string $connectionType, array $config): array
    {
        $baseResources = [
            'user_profile' => ['ttl' => 1800],
            'navigation_menu' => ['ttl' => 3600],
            'critical_notifications' => ['ttl' => 300],
        ];

        // Adjust based on connection type
        if (in_array($connectionType, ['slow-2g', '2g', '3g'])) {
            // More aggressive caching for slower connections
            foreach ($baseResources as &$resource) {
                $resource['ttl'] *= 2;
            }
        }

        return array_merge($baseResources, $config);
    }

    private function generatePreloadData(string $resource, HttpRequest $request, array $config): mixed
    {
        // This would integrate with your existing services
        return match($resource) {
            'user_profile' => $request->user()?->load(['tenant', 'roles']),
            'navigation_menu' => $this->generateNavigationMenu($request->user()),
            'critical_notifications' => $this->getCriticalNotifications($request->user()),
            default => null
        };
    }

    private function isImageUrl(string $url): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, $imageExtensions);
    }

    private function generateOptimizedImageUrl(string $originalUrl, string $connectionType): string
    {
        // Generate optimized image URL based on connection
        $quality = match($connectionType) {
            'slow-2g', '2g' => 40,
            '3g' => 60,
            '4g' => 80,
            default => 90
        };

        $width = match($connectionType) {
            'slow-2g', '2g' => 300,
            '3g' => 500,
            '4g' => 800,
            default => 1200
        };

        // This would integrate with your image optimization service
        return $originalUrl . "?w={$width}&q={$quality}&format=webp";
    }

    private function generateLazyLoadData(string $resource, array $params): array
    {
        // Placeholder - would integrate with your actual data services
        return ['lazy_loaded' => true, 'resource' => $resource, 'params' => $params];
    }

    private function optimizeLazyLoadForMobile(array $data, string $connectionType): array
    {
        // Apply mobile optimizations to lazy-loaded data
        return $this->optimizeImageUrls($data, $connectionType);
    }

    private function getLazyLoadCacheTTL(string $resource, string $connectionType): int
    {
        return $this->getMobileCacheTTL($connectionType, 1800); // 30 minutes default
    }

    private function logPerformanceMetrics(HttpRequest $request, array $metrics): void
    {
        $this->trackPerformanceMetrics($request, array_merge($metrics, [
            'response_optimization' => true,
            'timestamp' => microtime(true)
        ]));
    }

    // Additional helper methods for performance dashboard
    private function getPerformanceOverview(int $hours): array { return []; }
    private function getDeviceBreakdown(int $hours): array { return []; }
    private function getConnectionAnalysis(int $hours): array { return []; }
    private function getEndpointPerformance(int $hours): array { return []; }
    private function getCachePerformance(int $hours): array { return []; }
    private function getOptimizationImpact(int $hours): array { return []; }
    private function getRealTimeMetrics(): array { return []; }
    private function generateCacheStrategy(HttpRequest $request): array { return []; }
    private function getOfflinePages(HttpRequest $request): array { return []; }
    private function getSyncConfiguration(HttpRequest $request): array { return []; }
    private function getBackgroundSyncTasks(HttpRequest $request): array { return []; }
    private function getPushNotificationConfig(HttpRequest $request): array { return []; }
    private function getMobilePaginationLimit(string $connectionType): int { return 20; }
    private function getMobileOptimizedColumns($model): array { return ['*']; }
    private function detectPlatform(string $userAgent): string { return 'unknown'; }
    private function detectBrowser(string $userAgent): string { return 'unknown'; }
    private function generateNavigationMenu($user): array { return []; }
    private function getCriticalNotifications($user): array { return []; }
}