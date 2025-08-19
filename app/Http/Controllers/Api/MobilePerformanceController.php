<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobilePerformanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MobilePerformanceController extends Controller
{
    protected $mobilePerformanceService;

    public function __construct(MobilePerformanceService $mobilePerformanceService)
    {
        $this->mobilePerformanceService = $mobilePerformanceService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Get preloaded critical data for mobile applications.
     */
    public function preloadData(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // Get preload configuration from request
            $preloadConfig = $request->input('resources', []);
            
            // Get critical data for mobile
            $preloadData = $this->mobilePerformanceService->preloadCriticalData($request, $preloadConfig);
            
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Track performance metrics
            $this->mobilePerformanceService->trackPerformanceMetrics($request, [
                'endpoint' => 'preload_data',
                'response_time_ms' => $responseTime,
                'data_size_kb' => strlen(json_encode($preloadData)) / 1024,
                'resources_count' => count($preloadData),
                'cache_optimization' => true
            ]);

            return response()->json([
                'success' => true,
                'data' => $preloadData,
                'metadata' => [
                    'response_time_ms' => round($responseTime, 2),
                    'resources_loaded' => count($preloadData),
                    'cache_optimized' => true,
                    'mobile_optimized' => $this->mobilePerformanceService->isMobileDevice($request)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Mobile preload data error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load preload data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get lazy-loaded data for specific resources.
     */
    public function lazyLoadData(Request $request): JsonResponse
    {
        $request->validate([
            'resource' => 'required|string',
            'params' => 'array',
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:100'
        ]);

        $startTime = microtime(true);

        try {
            $resource = $request->input('resource');
            $params = $request->input('params', []);
            $offset = $request->input('offset', 0);
            $limit = $request->input('limit', 20);

            // Add pagination to params
            $params['offset'] = $offset;
            $params['limit'] = $limit;

            $data = $this->mobilePerformanceService->getLazyLoadData($resource, $params, $request);
            
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Track lazy loading performance
            $this->mobilePerformanceService->trackPerformanceMetrics($request, [
                'endpoint' => 'lazy_load_data',
                'resource' => $resource,
                'response_time_ms' => $responseTime,
                'data_size_kb' => strlen(json_encode($data)) / 1024,
                'offset' => $offset,
                'limit' => $limit
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'offset' => $offset,
                    'limit' => $limit,
                    'has_more' => count($data) === $limit
                ],
                'metadata' => [
                    'response_time_ms' => round($responseTime, 2),
                    'mobile_optimized' => $this->mobilePerformanceService->isMobileDevice($request)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Mobile lazy load error', [
                'resource' => $request->input('resource'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load resource data'
            ], 500);
        }
    }

    /**
     * Get offline capabilities and PWA configuration.
     */
    public function offlineCapabilities(Request $request): JsonResponse
    {
        try {
            $capabilities = $this->mobilePerformanceService->getOfflineCapabilities($request);

            return response()->json([
                'success' => true,
                'data' => $capabilities,
                'metadata' => [
                    'mobile_device' => $this->mobilePerformanceService->isMobileDevice($request),
                    'connection_type' => $this->mobilePerformanceService->getConnectionType($request),
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get offline capabilities'
            ], 500);
        }
    }

    /**
     * Record performance metrics from client-side.
     */
    public function recordMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'metrics' => 'required|array',
            'metrics.page_load_time' => 'numeric|min:0',
            'metrics.first_contentful_paint' => 'numeric|min:0',
            'metrics.largest_contentful_paint' => 'numeric|min:0',
            'metrics.cumulative_layout_shift' => 'numeric|min:0',
            'metrics.first_input_delay' => 'numeric|min:0',
            'metrics.network_requests' => 'integer|min:0',
            'metrics.cache_hits' => 'integer|min:0',
            'metrics.errors' => 'integer|min:0',
            'page' => 'string|max:255',
            'action' => 'string|max:255'
        ]);

        try {
            $metrics = $request->input('metrics');
            $page = $request->input('page', 'unknown');
            $action = $request->input('action', 'page_view');

            // Add client-side metrics to performance tracking
            $this->mobilePerformanceService->trackPerformanceMetrics($request, array_merge($metrics, [
                'source' => 'client',
                'page' => $page,
                'action' => $action,
                'recorded_at' => now()->toISOString()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Metrics recorded successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record client metrics', [
                'error' => $e->getMessage(),
                'metrics' => $request->input('metrics', [])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record metrics'
            ], 500);
        }
    }

    /**
     * Get mobile performance dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'integer|min:1|max:168' // Max 1 week
        ]);

        try {
            $hours = $request->input('hours', 24);
            
            $dashboard = $this->mobilePerformanceService->getPerformanceDashboard($hours);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'metadata' => [
                    'period_hours' => $hours,
                    'generated_at' => now()->toISOString(),
                    'cache_optimized' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Mobile performance dashboard error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load performance dashboard'
            ], 500);
        }
    }

    /**
     * Get device and connection information.
     */
    public function deviceInfo(Request $request): JsonResponse
    {
        try {
            $deviceInfo = $this->mobilePerformanceService->getDeviceInfo($request);
            $connectionType = $this->mobilePerformanceService->getConnectionType($request);

            return response()->json([
                'success' => true,
                'data' => array_merge($deviceInfo, [
                    'connection_type' => $connectionType,
                    'is_mobile_optimized' => $this->mobilePerformanceService->isMobileDevice($request),
                    'detected_at' => now()->toISOString()
                ])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get device information'
            ], 500);
        }
    }

    /**
     * Clear mobile performance caches.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $request->validate([
            'cache_type' => 'in:all,preload,lazy_load,metrics,images',
            'tenant_id' => 'integer|exists:tenants,id'
        ]);

        try {
            $cacheType = $request->input('cache_type', 'all');
            $tenantId = $request->input('tenant_id');

            $clearedItems = 0;

            switch ($cacheType) {
                case 'preload':
                    Cache::tags(['mobile_performance', 'preload'])->flush();
                    $clearedItems = 'preload cache';
                    break;

                case 'lazy_load':
                    Cache::tags(['mobile_performance', 'lazy_load'])->flush();
                    $clearedItems = 'lazy load cache';
                    break;

                case 'metrics':
                    Cache::tags(['mobile_performance', 'metrics'])->flush();
                    $clearedItems = 'metrics cache';
                    break;

                case 'images':
                    Cache::tags(['mobile_performance', 'images'])->flush();
                    $clearedItems = 'image cache';
                    break;

                case 'all':
                default:
                    Cache::tags(['mobile_performance'])->flush();
                    $clearedItems = 'all mobile performance caches';
                    break;
            }

            // Log cache clearing
            Log::info('Mobile performance cache cleared', [
                'cache_type' => $cacheType,
                'admin_user' => $request->user()->id,
                'tenant_id' => $tenantId
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully cleared {$clearedItems}",
                'data' => [
                    'cache_type' => $cacheType,
                    'cleared_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear mobile performance cache', [
                'error' => $e->getMessage(),
                'cache_type' => $request->input('cache_type')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache'
            ], 500);
        }
    }

    /**
     * Test mobile optimization on specific endpoint.
     */
    public function testOptimization(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:255',
            'method' => 'in:GET,POST,PUT,DELETE',
            'test_params' => 'array'
        ]);

        try {
            $endpoint = $request->input('endpoint');
            $method = $request->input('method', 'GET');
            $testParams = $request->input('test_params', []);

            $startTime = microtime(true);

            // Simulate the request with mobile optimization
            $testData = [
                'test_data' => ['items' => range(1, 50)], // Sample data
                'metadata' => ['total' => 50, 'per_page' => 20]
            ];

            $originalSize = strlen(json_encode($testData));
            $optimizedData = $this->mobilePerformanceService->optimizeResponse($testData, $request);
            $optimizedSize = strlen(json_encode($optimizedData));
            
            $responseTime = (microtime(true) - $startTime) * 1000;

            return response()->json([
                'success' => true,
                'data' => [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'device_info' => $this->mobilePerformanceService->getDeviceInfo($request),
                    'connection_type' => $this->mobilePerformanceService->getConnectionType($request),
                    'optimization_results' => [
                        'original_size_bytes' => $originalSize,
                        'optimized_size_bytes' => $optimizedSize,
                        'size_reduction_percent' => round((($originalSize - $optimizedSize) / $originalSize) * 100, 2),
                        'processing_time_ms' => round($responseTime, 2),
                        'mobile_optimized' => $this->mobilePerformanceService->isMobileDevice($request)
                    ],
                    'optimized_data' => $optimizedData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test optimization',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get performance recommendations for mobile.
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $deviceInfo = $this->mobilePerformanceService->getDeviceInfo($request);
            $connectionType = $this->mobilePerformanceService->getConnectionType($request);

            $recommendations = $this->generatePerformanceRecommendations($deviceInfo, $connectionType, $request);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'metadata' => [
                    'device_type' => $deviceInfo['type'],
                    'connection_type' => $connectionType,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate recommendations'
            ], 500);
        }
    }

    /**
     * Generate performance recommendations based on device and connection.
     */
    private function generatePerformanceRecommendations(array $deviceInfo, string $connectionType, Request $request): array
    {
        $recommendations = [];

        // Connection-based recommendations
        if (in_array($connectionType, ['slow-2g', '2g'])) {
            $recommendations[] = [
                'type' => 'critical',
                'category' => 'connection',
                'title' => 'Slow Connection Detected',
                'description' => 'Enable data saver mode and increase cache duration',
                'action' => 'enable_data_saver_mode'
            ];
        }

        if (in_array($connectionType, ['3g'])) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'connection',
                'title' => 'Moderate Connection Speed',
                'description' => 'Consider enabling image compression',
                'action' => 'enable_image_compression'
            ];
        }

        // Device-based recommendations
        if ($deviceInfo['type'] === 'mobile') {
            $recommendations[] = [
                'type' => 'info',
                'category' => 'device',
                'title' => 'Mobile Device Detected',
                'description' => 'Enable mobile-optimized layout and touch interactions',
                'action' => 'enable_mobile_layout'
            ];
        }

        // Performance recommendations
        $recommendations[] = [
            'type' => 'info',
            'category' => 'performance',
            'title' => 'Enable Offline Mode',
            'description' => 'Cache critical data for offline access',
            'action' => 'enable_offline_mode'
        ];

        $recommendations[] = [
            'type' => 'info',
            'category' => 'performance',
            'title' => 'Optimize Images',
            'description' => 'Use WebP format and responsive images',
            'action' => 'optimize_images'
        ];

        return $recommendations;
    }
}