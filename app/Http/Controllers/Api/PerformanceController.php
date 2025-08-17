<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PerformanceOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PerformanceController extends Controller
{
    public function __construct(
        private PerformanceOptimizer $optimizer
    ) {}

    /**
     * Track performance metrics from client
     */
    public function track(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metrics' => 'required|array',
            'metrics.*.name' => 'required|string',
            'metrics.*.value' => 'required|numeric',
            'metrics.*.timestamp' => 'required|numeric',
            'device_info' => 'array',
            'page_info' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid metrics data',
                'details' => $validator->errors()
            ], 400);
        }

        $deviceType = $this->getDeviceType($request);
        $connectionType = $this->getConnectionType($request);

        foreach ($request->input('metrics', []) as $metric) {
            $tags = [
                'device_type' => $deviceType,
                'connection_type' => $connectionType,
                'user_agent' => $request->userAgent(),
                'page' => $request->input('page_info.path', ''),
            ];

            $this->optimizer->trackPerformanceMetric(
                $metric['name'],
                $metric['value'],
                $tags
            );
        }

        return response()->json([
            'message' => 'Metrics tracked successfully',
            'tracked_count' => count($request->input('metrics', []))
        ]);
    }

    /**
     * Get performance analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $metric = $request->query('metric');
        $deviceType = $request->query('device_type');
        $days = (int) $request->query('days', 7);

        $analytics = $this->optimizer->getPerformanceAnalytics($metric, $deviceType);

        // Filter by date range
        $cutoff = now()->subDays($days)->format('Y-m-d');
        $analytics = array_filter($analytics, function ($item) use ($cutoff) {
            return $item['date'] >= $cutoff;
        });

        // Aggregate data
        $summary = $this->aggregateAnalytics($analytics);

        return response()->json([
            'summary' => $summary,
            'details' => $analytics,
            'period' => [
                'days' => $days,
                'from' => now()->subDays($days)->toDateString(),
                'to' => now()->toDateString(),
            ]
        ]);
    }

    /**
     * Get optimization recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $deviceType = $this->getDeviceType($request);
        $analytics = $this->optimizer->getPerformanceAnalytics(null, $deviceType);

        $recommendations = $this->generateRecommendations($analytics, $deviceType);

        return response()->json([
            'device_type' => $deviceType,
            'recommendations' => $recommendations,
            'priority_areas' => $this->getPriorityAreas($analytics),
        ]);
    }

    /**
     * Get device-optimized configuration
     */
    public function config(Request $request): JsonResponse
    {
        $deviceType = $this->getDeviceType($request);
        
        $config = [
            'lazy_loading' => $this->optimizer->getLazyLoadConfig(),
            'resource_hints' => $this->optimizer->getResourceHints($request),
            'device_specific' => $this->getDeviceSpecificConfig($deviceType),
            'performance_budget' => $this->getPerformanceBudget($deviceType),
        ];

        return response()->json($config);
    }

    /**
     * Real-time performance monitoring endpoint
     */
    public function monitor(Request $request): JsonResponse
    {
        $metrics = [
            'current_connections' => $this->getCurrentConnections(),
            'response_times' => $this->getAverageResponseTimes(),
            'error_rates' => $this->getErrorRates(),
            'device_breakdown' => $this->getDeviceBreakdown(),
            'performance_scores' => $this->getPerformanceScores(),
        ];

        return response()->json([
            'timestamp' => now()->toISOString(),
            'metrics' => $metrics,
            'status' => $this->getSystemHealthStatus($metrics),
        ]);
    }

    /**
     * Get device type from request
     */
    private function getDeviceType(Request $request): string
    {
        if ($request->header('X-Device-Type')) {
            return $request->header('X-Device-Type');
        }

        $userAgent = $request->userAgent();
        
        if (preg_match('/(tablet|ipad)/i', $userAgent)) {
            return 'tablet';
        }
        
        if (preg_match('/(mobile|phone|android|iphone)/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }

    /**
     * Get connection type from request headers
     */
    private function getConnectionType(Request $request): string
    {
        $saveData = $request->header('Save-Data');
        $downlink = $request->header('Downlink');
        $effectiveType = $request->header('ECT');

        if ($saveData === 'on') {
            return 'slow';
        }

        if ($effectiveType) {
            return $effectiveType;
        }

        if ($downlink) {
            $speed = (float) $downlink;
            if ($speed < 1) return 'slow';
            if ($speed < 5) return 'medium';
            return 'fast';
        }

        return 'unknown';
    }

    /**
     * Aggregate analytics data
     */
    private function aggregateAnalytics(array $analytics): array
    {
        if (empty($analytics)) {
            return [];
        }

        $metrics = [];
        foreach ($analytics as $item) {
            $metric = $item['metric'];
            if (!isset($metrics[$metric])) {
                $metrics[$metric] = [
                    'count' => 0,
                    'sum' => 0,
                    'min' => PHP_FLOAT_MAX,
                    'max' => 0,
                ];
            }

            $stats = $item['stats'];
            $metrics[$metric]['count'] += $stats['count'];
            $metrics[$metric]['sum'] += $stats['sum'];
            $metrics[$metric]['min'] = min($metrics[$metric]['min'], $stats['min']);
            $metrics[$metric]['max'] = max($metrics[$metric]['max'], $stats['max']);
        }

        // Calculate averages
        foreach ($metrics as $metric => &$data) {
            $data['avg'] = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0;
        }

        return $metrics;
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations(array $analytics, string $deviceType): array
    {
        $recommendations = [];

        foreach ($analytics as $item) {
            $metric = $item['metric'];
            $stats = $item['stats'];

            switch ($metric) {
                case 'page_load_time':
                    if ($stats['avg'] > 3000) { // > 3 seconds
                        $recommendations[] = [
                            'type' => 'critical',
                            'title' => 'Slow Page Load Times',
                            'description' => 'Average page load time is ' . round($stats['avg']/1000, 2) . 's. Consider optimizing critical resources.',
                            'actions' => [
                                'Enable resource compression',
                                'Optimize images for ' . $deviceType,
                                'Implement code splitting',
                                'Use CDN for static assets'
                            ]
                        ];
                    }
                    break;

                case 'first_contentful_paint':
                    if ($stats['avg'] > 2000) { // > 2 seconds
                        $recommendations[] = [
                            'type' => 'high',
                            'title' => 'Slow First Contentful Paint',
                            'description' => 'Users wait ' . round($stats['avg']/1000, 2) . 's to see content.',
                            'actions' => [
                                'Inline critical CSS',
                                'Optimize font loading',
                                'Reduce server response time',
                                'Minimize render-blocking resources'
                            ]
                        ];
                    }
                    break;

                case 'cumulative_layout_shift':
                    if ($stats['avg'] > 0.1) {
                        $recommendations[] = [
                            'type' => 'medium',
                            'title' => 'Layout Instability',
                            'description' => 'High Cumulative Layout Shift score of ' . round($stats['avg'], 3),
                            'actions' => [
                                'Set dimensions for images and videos',
                                'Reserve space for dynamic content',
                                'Avoid inserting content above existing content',
                                'Use CSS transforms instead of layout changes'
                            ]
                        ];
                    }
                    break;
            }
        }

        return $recommendations;
    }

    /**
     * Get priority areas for optimization
     */
    private function getPriorityAreas(array $analytics): array
    {
        $scores = [];
        
        foreach ($analytics as $item) {
            $metric = $item['metric'];
            $stats = $item['stats'];
            
            // Calculate priority score based on impact and current performance
            $score = 0;
            switch ($metric) {
                case 'page_load_time':
                    $score = min(100, ($stats['avg'] / 1000) * 20); // 5s = 100 points
                    break;
                case 'first_contentful_paint':
                    $score = min(100, ($stats['avg'] / 1000) * 30); // 3.3s = 100 points
                    break;
                case 'cumulative_layout_shift':
                    $score = min(100, $stats['avg'] * 400); // 0.25 = 100 points
                    break;
            }
            
            if ($score > 0) {
                $scores[$metric] = $score;
            }
        }

        arsort($scores);
        return array_slice($scores, 0, 5); // Top 5 priority areas
    }

    /**
     * Get device-specific configuration
     */
    private function getDeviceSpecificConfig(string $deviceType): array
    {
        $configs = [
            'mobile' => [
                'max_image_width' => 800,
                'compression_quality' => 70,
                'prefetch_limit' => 3,
                'cache_duration' => 300, // 5 minutes
                'batch_size' => 10,
            ],
            'tablet' => [
                'max_image_width' => 1200,
                'compression_quality' => 80,
                'prefetch_limit' => 5,
                'cache_duration' => 600, // 10 minutes
                'batch_size' => 20,
            ],
            'desktop' => [
                'max_image_width' => 1920,
                'compression_quality' => 90,
                'prefetch_limit' => 10,
                'cache_duration' => 900, // 15 minutes
                'batch_size' => 50,
            ],
        ];

        return $configs[$deviceType] ?? $configs['mobile'];
    }

    /**
     * Get performance budget for device type
     */
    private function getPerformanceBudget(string $deviceType): array
    {
        $budgets = [
            'mobile' => [
                'total_size' => 1024 * 1024, // 1MB
                'js_size' => 300 * 1024, // 300KB
                'css_size' => 100 * 1024, // 100KB
                'image_size' => 500 * 1024, // 500KB
                'font_size' => 100 * 1024, // 100KB
                'max_requests' => 50,
                'target_load_time' => 3000, // 3s
            ],
            'tablet' => [
                'total_size' => 2 * 1024 * 1024, // 2MB
                'js_size' => 500 * 1024, // 500KB
                'css_size' => 150 * 1024, // 150KB
                'image_size' => 1024 * 1024, // 1MB
                'font_size' => 150 * 1024, // 150KB
                'max_requests' => 75,
                'target_load_time' => 2500, // 2.5s
            ],
            'desktop' => [
                'total_size' => 3 * 1024 * 1024, // 3MB
                'js_size' => 800 * 1024, // 800KB
                'css_size' => 200 * 1024, // 200KB
                'image_size' => 1.5 * 1024 * 1024, // 1.5MB
                'font_size' => 200 * 1024, // 200KB
                'max_requests' => 100,
                'target_load_time' => 2000, // 2s
            ],
        ];

        return $budgets[$deviceType] ?? $budgets['mobile'];
    }

    /**
     * Get current connection count (mock implementation)
     */
    private function getCurrentConnections(): int
    {
        // In a real implementation, this would query actual connection metrics
        return rand(50, 200);
    }

    /**
     * Get average response times (mock implementation)
     */
    private function getAverageResponseTimes(): array
    {
        return [
            'mobile' => rand(200, 800),
            'tablet' => rand(150, 600),
            'desktop' => rand(100, 400),
        ];
    }

    /**
     * Get error rates (mock implementation)
     */
    private function getErrorRates(): array
    {
        return [
            '4xx' => rand(1, 5) / 100, // 1-5%
            '5xx' => rand(0, 2) / 100, // 0-2%
        ];
    }

    /**
     * Get device breakdown (mock implementation)
     */
    private function getDeviceBreakdown(): array
    {
        return [
            'mobile' => rand(40, 60),
            'tablet' => rand(15, 25),
            'desktop' => rand(20, 40),
        ];
    }

    /**
     * Get performance scores (mock implementation)
     */
    private function getPerformanceScores(): array
    {
        return [
            'mobile' => rand(70, 95),
            'tablet' => rand(75, 98),
            'desktop' => rand(85, 99),
        ];
    }

    /**
     * Get system health status
     */
    private function getSystemHealthStatus(array $metrics): string
    {
        $scores = $metrics['performance_scores'];
        $errors = $metrics['error_rates'];
        
        $avgScore = array_sum($scores) / count($scores);
        $totalErrors = $errors['4xx'] + $errors['5xx'];
        
        if ($avgScore >= 90 && $totalErrors < 0.05) {
            return 'excellent';
        } elseif ($avgScore >= 80 && $totalErrors < 0.10) {
            return 'good';
        } elseif ($avgScore >= 70 && $totalErrors < 0.15) {
            return 'fair';
        } else {
            return 'poor';
        }
    }
}