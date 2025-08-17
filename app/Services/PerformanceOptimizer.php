<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PerformanceOptimizer
{
    /**
     * Optimize data loading for mobile devices
     */
    public function optimizeForMobile(Request $request, array $data): array
    {
        $isMobile = $this->isMobileDevice($request);
        
        if (!$isMobile) {
            return $data;
        }
        
        // Reduce data payload for mobile
        return $this->reduceMobilePayload($data);
    }
    
    /**
     * Reduce data payload for mobile connections
     */
    private function reduceMobilePayload(array $data): array
    {
        $optimized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Limit array sizes for mobile
                if (count($value) > 50) {
                    $optimized[$key] = array_slice($value, 0, 50);
                    $optimized[$key . '_truncated'] = true;
                    $optimized[$key . '_total'] = count($value);
                } else {
                    $optimized[$key] = $this->reduceMobilePayload($value);
                }
            } elseif (is_string($value) && strlen($value) > 500) {
                // Truncate long strings for mobile
                $optimized[$key] = substr($value, 0, 500) . '...';
                $optimized[$key . '_truncated'] = true;
            } else {
                $optimized[$key] = $value;
            }
        }
        
        return $optimized;
    }
    
    /**
     * Cache API responses with mobile-specific TTL
     */
    public function cacheForMobile(string $key, $data, int $seconds = null): void
    {
        $mobileKey = "mobile:{$key}";
        $ttl = $seconds ?? $this->getMobileCacheTTL();
        
        Cache::put($mobileKey, $data, $ttl);
    }
    
    /**
     * Get cached data optimized for mobile
     */
    public function getCachedForMobile(string $key): mixed
    {
        $mobileKey = "mobile:{$key}";
        return Cache::get($mobileKey);
    }
    
    /**
     * Get mobile-specific cache TTL (shorter for fresher data)
     */
    private function getMobileCacheTTL(): int
    {
        return 300; // 5 minutes for mobile vs 15 minutes for desktop
    }
    
    /**
     * Optimize images for mobile delivery
     */
    public function optimizeImages(array $images, string $deviceType = 'mobile'): array
    {
        $optimized = [];
        
        $sizeMap = [
            'mobile' => ['w' => 400, 'h' => 300, 'q' => 70],
            'tablet' => ['w' => 800, 'h' => 600, 'q' => 80],
            'desktop' => ['w' => 1200, 'h' => 800, 'q' => 90],
        ];
        
        $params = $sizeMap[$deviceType] ?? $sizeMap['mobile'];
        
        foreach ($images as $image) {
            if (is_string($image)) {
                $optimized[] = $this->addImageParams($image, $params);
            } elseif (is_array($image) && isset($image['url'])) {
                $image['url'] = $this->addImageParams($image['url'], $params);
                $optimized[] = $image;
            } else {
                $optimized[] = $image;
            }
        }
        
        return $optimized;
    }
    
    /**
     * Add optimization parameters to image URL
     */
    private function addImageParams(string $url, array $params): string
    {
        $separator = strpos($url, '?') !== false ? '&' : '?';
        $queryParams = http_build_query($params);
        
        return $url . $separator . $queryParams;
    }
    
    /**
     * Implement data compression for API responses
     */
    public function compressResponse(array $data): array
    {
        // Remove null values to reduce payload
        $compressed = $this->removeNullValues($data);
        
        // Compress repeated structures
        $compressed = $this->compressRepeatedStructures($compressed);
        
        return $compressed;
    }
    
    /**
     * Remove null values recursively
     */
    private function removeNullValues(array $data): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                continue;
            }
            
            if (is_array($value)) {
                $cleaned = $this->removeNullValues($value);
                if (!empty($cleaned)) {
                    $result[$key] = $cleaned;
                }
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Compress repeated data structures
     */
    private function compressRepeatedStructures(array $data): array
    {
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }
        
        $items = $data['data'];
        if (count($items) < 2) {
            return $data;
        }
        
        // Find common keys across all items
        $firstItem = reset($items);
        if (!is_array($firstItem)) {
            return $data;
        }
        
        $commonKeys = array_keys($firstItem);
        foreach ($items as $item) {
            if (!is_array($item)) {
                return $data; // Mixed types, can't compress
            }
            $commonKeys = array_intersect($commonKeys, array_keys($item));
        }
        
        if (count($commonKeys) > 3) {
            // Extract schema and compress
            $schema = array_flip($commonKeys);
            $compressed = [];
            
            foreach ($items as $item) {
                $compressedItem = [];
                foreach ($commonKeys as $key) {
                    $compressedItem[] = $item[$key];
                }
                $compressed[] = $compressedItem;
            }
            
            $data['schema'] = $schema;
            $data['data'] = $compressed;
            $data['compressed'] = true;
        }
        
        return $data;
    }
    
    /**
     * Implement lazy loading configuration
     */
    public function getLazyLoadConfig(): array
    {
        return [
            'threshold' => 100, // pixels before entering viewport
            'batch_size' => 10, // items to load at once
            'delay' => 200, // ms delay between batches
            'placeholder' => '/images/placeholder.svg',
            'error' => '/images/error.svg',
        ];
    }
    
    /**
     * Generate resource hints for critical resources
     */
    public function getResourceHints(Request $request): array
    {
        $hints = [
            'preload' => [
                ['href' => '/css/app.css', 'as' => 'style'],
                ['href' => '/js/app.js', 'as' => 'script'],
            ],
            'prefetch' => [],
            'preconnect' => [],
        ];
        
        if ($this->isMobileDevice($request)) {
            // Mobile-specific resource hints
            $hints['preload'][] = ['href' => '/js/mobile-navigation.js', 'as' => 'script'];
            $hints['preload'][] = ['href' => '/js/touch-gestures.js', 'as' => 'script'];
            $hints['preload'][] = ['href' => '/js/pwa.js', 'as' => 'script'];
            
            // Prefetch critical mobile pages
            $hints['prefetch'] = [
                ['href' => '/dashboard'],
                ['href' => '/usage'],
                ['href' => '/profile'],
            ];
        }
        
        return $hints;
    }
    
    /**
     * Monitor performance metrics
     */
    public function trackPerformanceMetric(string $metric, float $value, array $tags = []): void
    {
        $key = "perf:{$metric}:" . date('Y-m-d-H');
        $data = [
            'metric' => $metric,
            'value' => $value,
            'timestamp' => microtime(true),
            'tags' => $tags,
        ];
        
        // Store in Redis for real-time monitoring
        Redis::lpush($key, json_encode($data));
        Redis::expire($key, 86400); // Keep for 24 hours
        
        // Also store aggregated metrics
        $this->updateAggregatedMetrics($metric, $value, $tags);
    }
    
    /**
     * Update aggregated performance metrics
     */
    private function updateAggregatedMetrics(string $metric, float $value, array $tags): void
    {
        $deviceType = $tags['device_type'] ?? 'unknown';
        $aggKey = "perf_agg:{$metric}:{$deviceType}:" . date('Y-m-d');
        
        $current = Cache::get($aggKey, [
            'count' => 0,
            'sum' => 0,
            'min' => PHP_FLOAT_MAX,
            'max' => 0,
        ]);
        
        $current['count']++;
        $current['sum'] += $value;
        $current['min'] = min($current['min'], $value);
        $current['max'] = max($current['max'], $value);
        $current['avg'] = $current['sum'] / $current['count'];
        
        Cache::put($aggKey, $current, 86400); // Keep for 24 hours
    }
    
    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics(string $metric = null, string $deviceType = null): array
    {
        $pattern = 'perf_agg:' . ($metric ?? '*') . ':' . ($deviceType ?? '*') . ':' . date('Y-m-d');
        $keys = Redis::keys($pattern);
        
        $analytics = [];
        foreach ($keys as $key) {
            $data = Cache::get($key);
            if ($data) {
                $parts = explode(':', $key);
                $analytics[] = [
                    'metric' => $parts[1],
                    'device_type' => $parts[2],
                    'date' => $parts[3],
                    'stats' => $data,
                ];
            }
        }
        
        return $analytics;
    }
    
    /**
     * Check if request is from mobile device
     */
    private function isMobileDevice(Request $request): bool
    {
        $userAgent = $request->header('User-Agent', '');
        
        $mobilePatterns = [
            '/Mobile/i',
            '/Android/i',
            '/iPhone/i',
            '/iPod/i',
            '/BlackBerry/i',
            '/Windows Phone/i',
        ];
        
        foreach ($mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
}