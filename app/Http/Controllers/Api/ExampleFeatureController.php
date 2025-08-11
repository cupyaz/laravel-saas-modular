<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExampleFeatureController extends Controller
{
    protected FeatureAccessService $featureAccessService;

    public function __construct(FeatureAccessService $featureAccessService)
    {
        $this->featureAccessService = $featureAccessService;
    }

    /**
     * Generate a basic report (freemium with limits).
     */
    public function generateBasicReport(Request $request): JsonResponse
    {
        // This endpoint is protected by the feature middleware
        // middleware('feature:basic_reports,reports_per_month')
        
        $user = $request->user();
        $tenant = $user->tenant;
        
        // Increment usage since the user successfully accessed this feature
        $this->featureAccessService->incrementUsage($tenant, 'basic_reports', 'reports_per_month');
        
        // Mock report generation
        $report = [
            'id' => rand(1000, 9999),
            'title' => 'Basic Report - ' . now()->format('Y-m-d'),
            'generated_at' => now(),
            'data' => [
                'total_users' => rand(10, 100),
                'active_users' => rand(5, 50),
                'revenue' => '$' . number_format(rand(1000, 10000), 2),
            ],
            'type' => 'basic',
        ];
        
        return response()->json([
            'message' => 'Report generated successfully',
            'report' => $report,
            'usage_info' => $this->featureAccessService->checkUsageLimit($tenant, 'basic_reports', 'reports_per_month'),
        ]);
    }

    /**
     * Access advanced analytics (premium feature).
     */
    public function getAdvancedAnalytics(Request $request): JsonResponse
    {
        // This endpoint is protected by the feature middleware
        // middleware('feature:advanced_analytics')
        
        // Mock advanced analytics data
        $analytics = [
            'user_behavior' => [
                'page_views' => rand(1000, 10000),
                'session_duration' => rand(120, 600),
                'bounce_rate' => rand(20, 60) . '%',
            ],
            'conversion_funnel' => [
                'visitors' => rand(1000, 5000),
                'signups' => rand(100, 500),
                'trials' => rand(50, 200),
                'conversions' => rand(10, 50),
            ],
            'revenue_analytics' => [
                'mrr' => '$' . number_format(rand(5000, 20000), 2),
                'arr' => '$' . number_format(rand(60000, 240000), 2),
                'churn_rate' => rand(2, 8) . '%',
                'ltv' => '$' . number_format(rand(500, 2000), 2),
            ],
            'feature_usage' => [
                'api_calls' => rand(10000, 100000),
                'storage_used' => rand(1000, 10000) . ' MB',
                'active_integrations' => rand(3, 10),
            ],
        ];
        
        return response()->json([
            'message' => 'Advanced analytics retrieved successfully',
            'analytics' => $analytics,
            'generated_at' => now(),
        ]);
    }

    /**
     * Upload file with storage limits.
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'name' => 'nullable|string|max:255',
        ]);
        
        // This endpoint is protected by the feature middleware
        // middleware('feature:file_storage,storage_mb')
        
        $user = $request->user();
        $tenant = $user->tenant;
        $file = $request->file('file');
        
        $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
        
        // Check if this upload would exceed storage limit
        $storageCheck = $this->featureAccessService->checkUsageLimit($tenant, 'file_storage', 'storage_mb');
        
        if ($storageCheck['limit_value'] !== -1 && 
            ($storageCheck['current_usage'] + $fileSizeMB) > $storageCheck['limit_value']) {
            return response()->json([
                'message' => 'Storage limit would be exceeded',
                'file_size_mb' => $fileSizeMB,
                'current_usage_mb' => $storageCheck['current_usage'],
                'limit_mb' => $storageCheck['limit_value'],
                'upgrade_info' => $this->featureAccessService->getUpgradeInfo($tenant, 'file_storage'),
            ], 413); // Payload Too Large
        }
        
        // Mock file upload (in reality, store to disk/cloud)
        $fileName = $request->name ?? $file->getClientOriginalName();
        $fileId = 'file_' . rand(100000, 999999);
        
        // Increment storage usage
        $this->featureAccessService->incrementUsage($tenant, 'file_storage', 'storage_mb', (int)ceil($fileSizeMB));
        
        return response()->json([
            'message' => 'File uploaded successfully',
            'file' => [
                'id' => $fileId,
                'name' => $fileName,
                'size' => $file->getSize(),
                'size_mb' => $fileSizeMB,
                'uploaded_at' => now(),
            ],
            'storage_info' => $this->featureAccessService->checkUsageLimit($tenant, 'file_storage', 'storage_mb'),
        ]);
    }

    /**
     * Create project with project limits.
     */
    public function createProject(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);
        
        // This endpoint is protected by the feature middleware
        // middleware('feature:projects,max_projects')
        
        $user = $request->user();
        $tenant = $user->tenant;
        
        // Increment project count
        $this->featureAccessService->incrementUsage($tenant, 'projects', 'max_projects');
        
        // Mock project creation
        $project = [
            'id' => rand(1000, 9999),
            'name' => $request->name,
            'description' => $request->description,
            'created_at' => now(),
            'status' => 'active',
        ];
        
        return response()->json([
            'message' => 'Project created successfully',
            'project' => $project,
            'project_info' => $this->featureAccessService->checkUsageLimit($tenant, 'projects', 'max_projects'),
        ]);
    }

    /**
     * Make API call with rate limits.
     */
    public function makeApiCall(Request $request): JsonResponse
    {
        // This endpoint is protected by the feature middleware
        // middleware('feature:api_access,api_calls_per_month')
        
        $user = $request->user();
        $tenant = $user->tenant;
        
        // Increment API usage
        $this->featureAccessService->incrementUsage($tenant, 'api_access', 'api_calls_per_month');
        
        // Mock API response
        $apiResponse = [
            'status' => 'success',
            'data' => [
                'message' => 'API call executed successfully',
                'timestamp' => now(),
                'request_id' => 'req_' . rand(100000, 999999),
            ],
            'meta' => [
                'rate_limit' => $this->featureAccessService->checkUsageLimit($tenant, 'api_access', 'api_calls_per_month'),
            ],
        ];
        
        return response()->json($apiResponse);
    }

    /**
     * Access custom branding (premium feature).
     */
    public function getCustomBranding(Request $request): JsonResponse
    {
        // This endpoint is protected by the feature middleware
        // middleware('feature:custom_branding')
        
        $user = $request->user();
        $tenant = $user->tenant;
        
        // Mock branding settings
        $branding = [
            'logo_url' => 'https://example.com/tenant-' . $tenant->id . '-logo.png',
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1F2937',
            'font_family' => 'Inter',
            'custom_css' => '.custom-header { background: linear-gradient(90deg, #3B82F6, #1F2937); }',
            'domain' => $tenant->slug . '.yourapp.com',
        ];
        
        return response()->json([
            'message' => 'Custom branding retrieved successfully',
            'branding' => $branding,
        ]);
    }

    /**
     * Export data (paid feature).
     */
    public function exportData(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|in:csv,json,xml,pdf',
            'data_type' => 'required|in:users,projects,reports,all',
        ]);
        
        // This endpoint is protected by the feature middleware
        // middleware('feature:export_data')
        
        $format = $request->format;
        $dataType = $request->data_type;
        
        // Mock data export
        $export = [
            'id' => 'export_' . rand(100000, 999999),
            'format' => $format,
            'data_type' => $dataType,
            'status' => 'processing',
            'created_at' => now(),
            'estimated_completion' => now()->addMinutes(5),
            'download_url' => null, // Will be available when processing completes
        ];
        
        return response()->json([
            'message' => 'Data export started successfully',
            'export' => $export,
        ]);
    }
}