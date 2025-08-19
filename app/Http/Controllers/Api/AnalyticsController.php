<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsReportExecution;
use App\Models\Module;
use App\Models\ModuleInstallation;
use App\Models\User;
use App\Models\Tenant;
use App\Services\AnalyticsService;
use App\Services\ReportGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected $analyticsService;
    protected $reportGenerator;

    public function __construct(AnalyticsService $analyticsService, ReportGeneratorService $reportGenerator)
    {
        $this->analyticsService = $analyticsService;
        $this->reportGenerator = $reportGenerator;
    }

    /**
     * Get main analytics dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'string|in:today,week,month,quarter,year',
            'tenant_id' => 'integer|exists:tenants,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->input('period', 'month');
        $tenantId = $request->input('tenant_id');

        // Get KPIs
        $kpis = $this->getDashboardKPIs($period, $tenantId);

        // Get charts data
        $charts = $this->getDashboardCharts($period, $tenantId);

        // Get recent activity
        $activity = $this->getRecentActivity($tenantId);

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => $kpis,
                'charts' => $charts,
                'activity' => $activity,
                'period' => $period,
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get user analytics data.
     */
    public function userAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'string|in:week,month,quarter,year',
            'tenant_id' => 'integer|exists:tenants,id',
            'metrics' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->input('period', 'month');
        $tenantId = $request->input('tenant_id');
        $requestedMetrics = $request->input('metrics', [
            'total_users', 'active_users', 'new_registrations', 'user_retention'
        ]);

        $analytics = $this->analyticsService->getUserAnalytics($period, $tenantId, $requestedMetrics);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get module analytics data.
     */
    public function moduleAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'string|in:week,month,quarter,year',
            'tenant_id' => 'integer|exists:tenants,id',
            'category' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->input('period', 'month');
        $tenantId = $request->input('tenant_id');
        $category = $request->input('category');

        $analytics = $this->analyticsService->getModuleAnalytics($period, $tenantId, $category);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get revenue analytics data.
     */
    public function revenueAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'period' => 'string|in:week,month,quarter,year',
            'tenant_id' => 'integer|exists:tenants,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $period = $request->input('period', 'month');
        $tenantId = $request->input('tenant_id');

        $analytics = $this->analyticsService->getRevenueAnalytics($period, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get available metrics for analytics.
     */
    public function getAvailableMetrics(): JsonResponse
    {
        $metrics = AnalyticsMetric::getAvailableMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get custom analytics data.
     */
    public function customAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metrics' => 'required|array|min:1',
            'dimensions' => 'array',
            'filters' => 'array',
            'date_range' => 'array',
            'date_range.start' => 'required_with:date_range|date',
            'date_range.end' => 'required_with:date_range|date|after:date_range.start',
            'aggregation' => 'string|in:sum,avg,min,max,count',
            'group_by' => 'string|in:day,week,month'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $metrics = $request->input('metrics');
        $dimensions = $request->input('dimensions', []);
        $filters = $request->input('filters', []);
        $dateRange = $request->input('date_range');
        $aggregation = $request->input('aggregation', 'sum');
        $groupBy = $request->input('group_by', 'day');

        $startDate = $dateRange ? Carbon::parse($dateRange['start']) : now()->subDays(30);
        $endDate = $dateRange ? Carbon::parse($dateRange['end']) : now();

        $results = [];

        foreach ($metrics as $metricName) {
            if ($groupBy) {
                $results[$metricName] = AnalyticsMetric::getTimeSeries(
                    $metricName,
                    $groupBy,
                    $filters['tenant_id'] ?? null,
                    $startDate,
                    $endDate
                );
            } else {
                $results[$metricName] = AnalyticsMetric::getAggregatedValue(
                    $metricName,
                    $aggregation,
                    $filters['tenant_id'] ?? null,
                    $startDate,
                    $endDate
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'parameters' => [
                    'metrics' => $metrics,
                    'date_range' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString()
                    ],
                    'aggregation' => $aggregation,
                    'group_by' => $groupBy
                ]
            ]
        ]);
    }

    /**
     * Create a new analytics report.
     */
    public function createReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'string|max:500',
            'type' => 'required|string|in:' . implode(',', array_keys(AnalyticsReport::getAvailableTypes())),
            'category' => 'string|in:' . implode(',', array_keys(AnalyticsReport::getAvailableCategories())),
            'metrics' => 'required|array|min:1',
            'dimensions' => 'array',
            'filters' => 'array',
            'date_range' => 'array',
            'date_range.start' => 'required_with:date_range|date',
            'date_range.end' => 'required_with:date_range|date|after:date_range.start',
            'schedule' => 'array',
            'schedule.type' => 'string|in:once,daily,weekly,monthly,quarterly',
            'format' => 'required|string|in:' . implode(',', array_keys(AnalyticsReport::getAvailableFormats())),
            'recipients' => 'array',
            'is_public' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Additional validation
        $configErrors = AnalyticsReport::validateConfiguration($request->all());
        if (!empty($configErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration validation failed',
                'errors' => $configErrors
            ], 422);
        }

        $report = AnalyticsReport::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'category' => $request->input('category', AnalyticsReport::CATEGORY_DASHBOARD),
            'metrics' => $request->input('metrics'),
            'dimensions' => $request->input('dimensions', []),
            'filters' => $request->input('filters', []),
            'date_range' => $request->input('date_range'),
            'schedule' => $request->input('schedule'),
            'format' => $request->input('format'),
            'recipients' => $request->input('recipients', []),
            'is_public' => $request->boolean('is_public'),
            'is_active' => true,
            'created_by' => $request->user()->id,
            'tenant_id' => $request->user()->tenant_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report created successfully',
            'data' => $report
        ], 201);
    }

    /**
     * Get list of analytics reports.
     */
    public function getReports(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'string',
            'category' => 'string',
            'is_public' => 'boolean',
            'per_page' => 'integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = AnalyticsReport::query();

        // Apply filters
        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        if ($request->has('is_public')) {
            if ($request->boolean('is_public')) {
                $query->public();
            }
        }

        // Access control
        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('is_public', true)
                  ->orWhere('created_by', $user->id)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('tenant_id', $user->tenant_id)
                         ->whereHas('tenant.users', function ($q3) use ($user) {
                             $q3->where('users.id', $user->id)
                                ->where('is_admin', true);
                         });
                  });
            });
        }

        $perPage = $request->input('per_page', 15);
        $reports = $query->with(['creator', 'tenant'])
                        ->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total()
            ]
        ]);
    }

    /**
     * Execute a report.
     */
    public function executeReport(Request $request, int $reportId): JsonResponse
    {
        $report = AnalyticsReport::find($reportId);
        
        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found'
            ], 404);
        }

        if (!$report->canAccess($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        if (!$report->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Report is not active'
            ], 422);
        }

        try {
            $execution = $this->reportGenerator->executeReport($report, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Report execution started',
                'data' => [
                    'execution_id' => $execution->id,
                    'status' => $execution->status,
                    'started_at' => $execution->started_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report execution status.
     */
    public function getExecutionStatus(int $executionId): JsonResponse
    {
        $execution = AnalyticsReportExecution::with(['report', 'executor'])
                                            ->find($executionId);
        
        if (!$execution) {
            return response()->json([
                'success' => false,
                'message' => 'Execution not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $execution->id,
                'report_name' => $execution->report->name,
                'status' => $execution->status,
                'status_text' => $execution->getStatusText(),
                'started_at' => $execution->started_at,
                'completed_at' => $execution->completed_at,
                'duration' => $execution->duration,
                'file_size' => $execution->getFormattedFileSize(),
                'error_message' => $execution->error_message,
                'can_download' => $execution->isCompleted() && $execution->file_path
            ]
        ]);
    }

    /**
     * Download report file.
     */
    public function downloadReport(int $executionId): JsonResponse
    {
        $execution = AnalyticsReportExecution::find($executionId);
        
        if (!$execution || !$execution->isCompleted() || !$execution->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Report file not available'
            ], 404);
        }

        if (!file_exists($execution->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report file not found on disk'
            ], 404);
        }

        return response()->download($execution->file_path);
    }

    /**
     * Get dashboard KPIs.
     */
    private function getDashboardKPIs(string $period, ?int $tenantId): array
    {
        $endDate = now();
        $startDate = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth()
        };

        return [
            'users' => [
                'total' => AnalyticsMetric::getAggregatedValue('total_users', 'max', $tenantId, $startDate, $endDate),
                'active' => AnalyticsMetric::getAggregatedValue('active_users_30d', 'max', $tenantId, $startDate, $endDate),
                'new' => AnalyticsMetric::getAggregatedValue('new_registrations', 'sum', $tenantId, $startDate, $endDate)
            ],
            'modules' => [
                'total' => AnalyticsMetric::getAggregatedValue('total_modules', 'max', $tenantId, $startDate, $endDate),
                'active' => AnalyticsMetric::getAggregatedValue('active_modules', 'max', $tenantId, $startDate, $endDate),
                'installations' => AnalyticsMetric::getAggregatedValue('active_module_installations', 'max', $tenantId, $startDate, $endDate)
            ],
            'revenue' => [
                'potential' => AnalyticsMetric::getAggregatedValue('potential_module_revenue', 'max', $tenantId, $startDate, $endDate)
            ],
            'performance' => [
                'avg_response_time' => AnalyticsMetric::getAggregatedValue('avg_response_time', 'avg', $tenantId, $startDate, $endDate),
                'uptime' => AnalyticsMetric::getAggregatedValue('uptime_percentage', 'avg', $tenantId, $startDate, $endDate)
            ]
        ];
    }

    /**
     * Get dashboard charts data.
     */
    private function getDashboardCharts(string $period, ?int $tenantId): array
    {
        $interval = match($period) {
            'today' => 'hour',
            'week' => 'day',
            'month' => 'day',
            'quarter' => 'week',
            'year' => 'month',
            default => 'day'
        };

        $endDate = now();
        $startDate = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth()
        };

        return [
            'user_growth' => AnalyticsMetric::getTimeSeries('new_registrations', $interval, $tenantId, $startDate, $endDate),
            'module_installations' => AnalyticsMetric::getTimeSeries('active_module_installations', $interval, $tenantId, $startDate, $endDate),
            'performance' => AnalyticsMetric::getTimeSeries('avg_response_time', $interval, $tenantId, $startDate, $endDate)
        ];
    }

    /**
     * Get recent activity.
     */
    private function getRecentActivity(?int $tenantId): array
    {
        $activities = [];

        // Recent module installations
        $recentInstallations = ModuleInstallation::with(['module', 'tenant'])
                                                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                                                ->latest()
                                                ->limit(5)
                                                ->get();

        foreach ($recentInstallations as $installation) {
            $activities[] = [
                'type' => 'module_installation',
                'message' => "Module '{$installation->module->name}' installed by {$installation->tenant->name}",
                'timestamp' => $installation->created_at,
                'metadata' => [
                    'module' => $installation->module->name,
                    'tenant' => $installation->tenant->name,
                    'status' => $installation->status
                ]
            ];
        }

        // Recent report executions
        $recentExecutions = AnalyticsReportExecution::with(['report', 'executor'])
                                                   ->latest()
                                                   ->limit(5)
                                                   ->get();

        foreach ($recentExecutions as $execution) {
            $activities[] = [
                'type' => 'report_execution',
                'message' => "Report '{$execution->report->name}' executed by {$execution->executor->name}",
                'timestamp' => $execution->created_at,
                'metadata' => [
                    'report' => $execution->report->name,
                    'executor' => $execution->executor->name,
                    'status' => $execution->status
                ]
            ];
        }

        // Sort by timestamp
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, 10);
    }
}