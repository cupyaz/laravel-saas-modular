<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\MobilePerformanceService;
use Illuminate\Support\Facades\Log;

class MobileOptimization
{
    protected $mobilePerformanceService;

    public function __construct(MobilePerformanceService $mobilePerformanceService)
    {
        $this->mobilePerformanceService = $mobilePerformanceService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Only optimize for mobile devices and JSON responses
        if (!$this->shouldOptimize($request, $response)) {
            return $response;
        }

        try {
            // Optimize the response for mobile
            $optimizedResponse = $this->optimizeResponse($request, $response);
            
            // Add performance headers
            $this->addPerformanceHeaders($optimizedResponse, $request, $startTime);
            
            // Track performance metrics
            $this->trackPerformanceMetrics($request, $startTime, $response, $optimizedResponse);

            return $optimizedResponse;

        } catch (\Exception $e) {
            Log::error('Mobile optimization failed', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'user_id' => $request->user()?->id
            ]);

            // Return original response if optimization fails
            return $response;
        }
    }

    /**
     * Determine if the response should be optimized.
     */
    private function shouldOptimize(Request $request, $response): bool
    {
        // Check if it's a mobile device
        if (!$this->mobilePerformanceService->isMobileDevice($request)) {
            return false;
        }

        // Check if it's a JSON response
        if (!$response instanceof JsonResponse) {
            return false;
        }

        // Skip optimization for certain routes
        $skipRoutes = ['api/mobile/performance/test', 'api/debug'];
        foreach ($skipRoutes as $skipRoute) {
            if ($request->is($skipRoute)) {
                return false;
            }
        }

        // Check if optimization is enabled
        return config('mobile.optimization.enabled', true);
    }

    /**
     * Optimize the JSON response for mobile.
     */
    private function optimizeResponse(Request $request, JsonResponse $response): JsonResponse
    {
        $originalData = $response->getData(true);
        
        if (!is_array($originalData)) {
            return $response;
        }

        // Apply mobile optimizations
        $optimizedData = $this->mobilePerformanceService->optimizeResponse($originalData, $request);

        // Add mobile-specific metadata
        $optimizedData = $this->addMobileMetadata($optimizedData, $request);

        // Create optimized response
        $optimizedResponse = response()->json($optimizedData, $response->getStatusCode());

        // Copy original headers
        foreach ($response->headers->all() as $key => $values) {
            $optimizedResponse->headers->set($key, $values);
        }

        return $optimizedResponse;
    }

    /**
     * Add mobile-specific metadata to the response.
     */
    private function addMobileMetadata(array $data, Request $request): array
    {
        $connectionType = $this->mobilePerformanceService->getConnectionType($request);
        
        // Add mobile context to metadata
        if (!isset($data['_mobile'])) {
            $data['_mobile'] = [
                'optimized' => true,
                'connection_type' => $connectionType,
                'optimization_level' => $this->getOptimizationLevel($connectionType),
                'cache_strategy' => $this->getCacheStrategy($connectionType),
                'preload_enabled' => config('mobile.preload.enabled', true),
                'lazy_load_enabled' => config('mobile.lazy_load.enabled', true)
            ];
        }

        return $data;
    }

    /**
     * Add performance headers to the response.
     */
    private function addPerformanceHeaders(JsonResponse $response, Request $request, float $startTime): void
    {
        $processingTime = (microtime(true) - $startTime) * 1000;
        $responseSize = strlen($response->getContent());
        
        $response->headers->add([
            'X-Mobile-Optimized' => 'true',
            'X-Processing-Time' => round($processingTime, 2) . 'ms',
            'X-Response-Size' => $responseSize . 'b',
            'X-Connection-Type' => $this->mobilePerformanceService->getConnectionType($request),
            'X-Device-Type' => $this->mobilePerformanceService->isMobileDevice($request) ? 'mobile' : 'desktop',
            'X-Cache-Strategy' => $this->getCacheStrategy($this->mobilePerformanceService->getConnectionType($request)),
        ]);

        // Add caching headers based on connection type
        $this->addCachingHeaders($response, $request);
    }

    /**
     * Add appropriate caching headers based on connection type.
     */
    private function addCachingHeaders(JsonResponse $response, Request $request): void
    {
        $connectionType = $this->mobilePerformanceService->getConnectionType($request);
        
        $cacheMaxAge = match($connectionType) {
            'slow-2g', '2g' => 86400, // 24 hours for slow connections
            '3g' => 7200,             // 2 hours for 3G
            '4g' => 3600,             // 1 hour for 4G
            default => 1800           // 30 minutes default
        };

        $response->headers->add([
            'Cache-Control' => "public, max-age={$cacheMaxAge}, must-revalidate",
            'Vary' => 'User-Agent, Network-Information',
            'X-Cache-TTL' => $cacheMaxAge . 's'
        ]);

        // Add ETag for better caching
        $etag = md5($response->getContent() . $connectionType);
        $response->headers->set('ETag', $etag);
    }

    /**
     * Track performance metrics for the optimization process.
     */
    private function trackPerformanceMetrics(Request $request, float $startTime, $originalResponse, $optimizedResponse): void
    {
        $processingTime = (microtime(true) - $startTime) * 1000;
        $originalSize = strlen($originalResponse->getContent());
        $optimizedSize = strlen($optimizedResponse->getContent());
        
        $sizeReduction = $originalSize > 0 ? (($originalSize - $optimizedSize) / $originalSize) * 100 : 0;

        $this->mobilePerformanceService->trackPerformanceMetrics($request, [
            'middleware' => 'mobile_optimization',
            'processing_time_ms' => $processingTime,
            'original_size_bytes' => $originalSize,
            'optimized_size_bytes' => $optimizedSize,
            'size_reduction_percent' => round($sizeReduction, 2),
            'optimization_applied' => true,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => $optimizedResponse->getStatusCode()
        ]);
    }

    /**
     * Get optimization level based on connection type.
     */
    private function getOptimizationLevel(string $connectionType): string
    {
        return match($connectionType) {
            'slow-2g', '2g' => 'aggressive',
            '3g' => 'moderate',
            '4g' => 'light',
            default => 'standard'
        };
    }

    /**
     * Get cache strategy based on connection type.
     */
    private function getCacheStrategy(string $connectionType): string
    {
        return match($connectionType) {
            'slow-2g', '2g' => 'extended',
            '3g' => 'standard',
            '4g' => 'light',
            default => 'auto'
        };
    }
}