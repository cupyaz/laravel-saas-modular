<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Models\ModuleInstallation;
use App\Models\ModuleVersion;
use App\Models\ModuleReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    /**
     * Get module marketplace with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category' => 'string|in:' . implode(',', array_keys(Module::CATEGORIES)),
            'search' => 'string|max:255',
            'featured' => 'boolean',
            'price' => 'string|in:free,paid,all',
            'sort' => 'string|in:name,rating,downloads,price,created_at',
            'direction' => 'string|in:asc,desc',
            'per_page' => 'integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Module::active();

        // Apply filters
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        if ($request->filled('price')) {
            switch ($request->price) {
                case 'free':
                    $query->where('price', 0);
                    break;
                case 'paid':
                    $query->where('price', '>', 0);
                    break;
            }
        }

        // Apply sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        
        switch ($sort) {
            case 'rating':
                $query->orderBy('rating', $direction);
                break;
            case 'downloads':
                $query->orderBy('download_count', $direction);
                break;
            case 'price':
                $query->orderBy('price', $direction);
                break;
            case 'created_at':
                $query->orderBy('created_at', $direction);
                break;
            default:
                $query->orderBy('name', $direction);
        }

        $perPage = $request->input('per_page', 15);
        $modules = $query->with(['versions' => function ($q) {
            $q->stable()->latest('version');
        }])->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ModuleResource::collection($modules->items()),
            'meta' => [
                'current_page' => $modules->currentPage(),
                'last_page' => $modules->lastPage(),
                'per_page' => $modules->perPage(),
                'total' => $modules->total(),
                'categories' => Module::CATEGORIES,
                'filters_applied' => array_filter([
                    'category' => $request->category,
                    'search' => $request->search,
                    'featured' => $request->boolean('featured') ?: null,
                    'price' => $request->price,
                ])
            ]
        ]);
    }

    /**
     * Get module details with full information.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $module = Module::where('slug', $slug)
                        ->with(['versions', 'reviews.user', 'installations'])
                        ->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        // Get installation status for current tenant
        $tenantId = $request->user()?->tenant_id;
        $installation = $module->getInstallationStatus($tenantId);

        // Get compatibility info
        $compatibility = [
            'is_compatible' => $module->isCompatible(),
            'issues' => $module->getCompatibilityIssues()
        ];

        // Get review statistics
        $reviewStats = [
            'average_rating' => $module->rating,
            'total_reviews' => $module->reviews()->approved()->count(),
            'rating_distribution' => $this->getRatingDistribution($module->id),
            'recent_reviews' => ModuleResource::collection(
                $module->reviews()
                       ->approved()
                       ->with('user')
                       ->latest()
                       ->limit(5)
                       ->get()
            )
        ];

        return response()->json([
            'success' => true,
            'data' => new ModuleResource($module),
            'meta' => [
                'installation' => $installation ? [
                    'status' => $installation->status,
                    'version' => $installation->version,
                    'installed_at' => $installation->installed_at,
                    'health_score' => $installation->getHealthScore()
                ] : null,
                'compatibility' => $compatibility,
                'reviews' => $reviewStats,
                'latest_version' => $module->versions()->stable()->latest('version')->first()?->version
            ]
        ]);
    }

    /**
     * Install a module for the current tenant.
     */
    public function install(Request $request, string $slug): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'version' => 'string|nullable',
            'config' => 'array|nullable',
            'auto_update' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $module = Module::where('slug', $slug)->active()->first();
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found or not active'
            ], 404);
        }

        $tenantId = $request->user()->tenant_id;
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Installation requires tenant context'
            ], 400);
        }

        // Check if already installed
        $existingInstallation = $module->getInstallationStatus($tenantId);
        if ($existingInstallation && $existingInstallation->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Module is already installed and active'
            ], 409);
        }

        // Check compatibility
        if (!$module->isCompatible()) {
            return response()->json([
                'success' => false,
                'message' => 'Module is not compatible with current system',
                'errors' => ['compatibility' => $module->getCompatibilityIssues()]
            ], 422);
        }

        // Determine version to install
        $version = $request->input('version');
        if (!$version) {
            $latestVersion = $module->getLatestVersion();
            if (!$latestVersion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No stable version available for installation'
                ], 422);
            }
            $version = $latestVersion->version;
        }

        // Validate configuration if provided
        $config = $request->input('config', []);
        if (!empty($config)) {
            $configErrors = $module->validateConfig($config);
            if (!empty($configErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration validation failed',
                    'errors' => ['config' => $configErrors]
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            // Create or update installation
            if ($existingInstallation) {
                $installation = $existingInstallation;
                $installation->update([
                    'version' => $version,
                    'status' => ModuleInstallation::STATUS_INSTALLING,
                    'config' => array_merge($module->getDefaultConfig(), $config),
                    'auto_update' => $request->boolean('auto_update', false),
                    'installation_method' => ModuleInstallation::METHOD_API,
                    'error_log' => []
                ]);
            } else {
                $installation = ModuleInstallation::create([
                    'module_id' => $module->id,
                    'tenant_id' => $tenantId,
                    'version' => $version,
                    'status' => ModuleInstallation::STATUS_INSTALLING,
                    'config' => array_merge($module->getDefaultConfig(), $config),
                    'installed_at' => now(),
                    'auto_update' => $request->boolean('auto_update', false),
                    'installation_method' => ModuleInstallation::METHOD_API,
                    'installation_source' => 'marketplace'
                ]);
            }

            // Simulate installation process (in real app, this would be queued)
            $success = $this->performInstallation($installation);

            if ($success) {
                $installation->update([
                    'status' => ModuleInstallation::STATUS_ACTIVE,
                    'activated_at' => now()
                ]);

                // Increment download count
                $module->incrementDownloads();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Module installed successfully',
                    'data' => [
                        'installation_id' => $installation->id,
                        'status' => $installation->status,
                        'version' => $installation->version,
                        'health_score' => $installation->getHealthScore()
                    ]
                ]);
            } else {
                $installation->update(['status' => ModuleInstallation::STATUS_ERROR]);
                
                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Module installation failed',
                    'errors' => ['installation' => $installation->error_log]
                ], 422);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Installation failed due to system error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update module configuration.
     */
    public function updateConfig(Request $request, string $slug): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'config' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $module = Module::where('slug', $slug)->first();
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        $tenantId = $request->user()->tenant_id;
        $installation = $module->getInstallationStatus($tenantId);

        if (!$installation) {
            return response()->json([
                'success' => false,
                'message' => 'Module is not installed'
            ], 404);
        }

        $config = $request->input('config');
        $configErrors = $module->validateConfig($config);
        
        if (!empty($configErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration validation failed',
                'errors' => ['config' => $configErrors]
            ], 422);
        }

        $installation->update(['config' => $config]);

        return response()->json([
            'success' => true,
            'message' => 'Configuration updated successfully',
            'data' => [
                'config' => $installation->getEffectiveConfig()
            ]
        ]);
    }

    /**
     * Uninstall a module.
     */
    public function uninstall(Request $request, string $slug): JsonResponse
    {
        $module = Module::where('slug', $slug)->first();
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        $tenantId = $request->user()->tenant_id;
        $installation = $module->getInstallationStatus($tenantId);

        if (!$installation) {
            return response()->json([
                'success' => false,
                'message' => 'Module is not installed'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $installation->update(['status' => ModuleInstallation::STATUS_UNINSTALLING]);

            // Perform uninstallation (in real app, this would be queued)
            $this->performUninstallation($installation);

            // Soft delete the installation
            $installation->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Module uninstalled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $installation->logError('Uninstallation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Uninstallation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get installed modules for current tenant.
     */
    public function installed(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $installations = ModuleInstallation::where('tenant_id', $tenantId)
                                          ->with(['module', 'module.versions'])
                                          ->get();

        $installedModules = $installations->map(function ($installation) {
            return [
                'module' => new ModuleResource($installation->module),
                'installation' => [
                    'id' => $installation->id,
                    'status' => $installation->status,
                    'status_text' => $installation->getStatusText(),
                    'status_color' => $installation->getStatusBadgeColor(),
                    'version' => $installation->version,
                    'installed_at' => $installation->installed_at,
                    'health_score' => $installation->getHealthScore(),
                    'can_update' => $installation->canUpdate(),
                    'next_version' => $installation->getNextVersion()?->version,
                    'needs_attention' => $installation->needsAttention(),
                    'config' => $installation->getEffectiveConfig()
                ]
            ];
        });

        $summary = [
            'total' => $installations->count(),
            'active' => $installations->where('status', ModuleInstallation::STATUS_ACTIVE)->count(),
            'inactive' => $installations->where('status', ModuleInstallation::STATUS_INACTIVE)->count(),
            'error' => $installations->where('status', ModuleInstallation::STATUS_ERROR)->count(),
            'needs_attention' => $installations->filter(fn($i) => $i->needsAttention())->count(),
            'can_update' => $installations->filter(fn($i) => $i->canUpdate())->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $installedModules,
            'meta' => [
                'summary' => $summary,
                'tenant_id' => $tenantId
            ]
        ]);
    }

    /**
     * Get module categories.
     */
    public function categories(): JsonResponse
    {
        $categories = collect(Module::CATEGORIES)->map(function ($name, $slug) {
            return [
                'slug' => $slug,
                'name' => $name,
                'count' => Module::active()->byCategory($slug)->count()
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get module reviews.
     */
    public function reviews(Request $request, string $slug): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'integer|min:1|max:5',
            'sort' => 'string|in:newest,oldest,helpful,rating',
            'per_page' => 'integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $module = Module::where('slug', $slug)->first();
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        $query = $module->reviews()->approved()->with('user');

        // Apply filters
        if ($request->filled('rating')) {
            $query->byRating($request->rating);
        }

        // Apply sorting
        switch ($request->input('sort', 'newest')) {
            case 'oldest':
                $query->oldest();
                break;
            case 'helpful':
                $query->byHelpfulness();
                break;
            case 'rating':
                $query->orderBy('rating', 'desc');
                break;
            default:
                $query->latest();
        }

        $perPage = $request->input('per_page', 10);
        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'rating_distribution' => $this->getRatingDistribution($module->id)
            ]
        ]);
    }

    /**
     * Simulate module installation process.
     */
    private function performInstallation(ModuleInstallation $installation): bool
    {
        try {
            // Simulate installation steps
            sleep(1); // Simulate file download
            sleep(1); // Simulate extraction
            sleep(1); // Simulate configuration

            // Random chance of failure for testing
            if (rand(1, 100) <= 5) { // 5% failure rate
                $installation->logError('Installation failed during setup phase', [
                    'phase' => 'setup',
                    'reason' => 'Simulated failure for testing'
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $installation->logError('Installation exception', [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Simulate module uninstallation process.
     */
    private function performUninstallation(ModuleInstallation $installation): bool
    {
        try {
            // Simulate cleanup steps
            sleep(1); // Simulate file removal
            sleep(1); // Simulate database cleanup

            return true;

        } catch (\Exception $e) {
            $installation->logError('Uninstallation exception', [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get rating distribution for a module.
     */
    private function getRatingDistribution(int $moduleId): array
    {
        $distribution = [];
        
        for ($rating = 1; $rating <= 5; $rating++) {
            $count = ModuleReview::where('module_id', $moduleId)
                                ->approved()
                                ->where('rating', $rating)
                                ->count();
            
            $distribution[$rating] = $count;
        }

        return $distribution;
    }
}