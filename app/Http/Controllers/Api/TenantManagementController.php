<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Subscription;
use App\Models\AnalyticsMetric;
use App\Models\ModuleInstallation;
use App\Services\TenantManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TenantManagementController extends Controller
{
    protected $tenantManagementService;

    public function __construct(TenantManagementService $tenantManagementService)
    {
        $this->tenantManagementService = $tenantManagementService;
        $this->middleware('auth:sanctum');
        $this->middleware('admin'); // Ensure only super admins can access
    }

    /**
     * Get multi-tenant management dashboard overview.
     */
    public function dashboard(): JsonResponse
    {
        $cacheKey = 'tenant_management_dashboard_' . now()->format('Y-m-d-H');
        
        $dashboard = Cache::remember($cacheKey, 3600, function () {
            return $this->generateDashboardData();
        });

        return response()->json([
            'success' => true,
            'data' => $dashboard,
            'generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Get paginated list of all tenants with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'status' => 'in:active,suspended,pending,cancelled',
            'subscription_status' => 'in:active,paused,cancelled,expired',
            'sort_by' => 'in:name,created_at,updated_at,users_count,subscription_value',
            'sort_direction' => 'in:asc,desc'
        ]);

        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');
        $status = $request->get('status');
        $subscriptionStatus = $request->get('subscription_status');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $query = Tenant::with(['users', 'subscription', 'modules'])
                      ->withCount(['users', 'moduleInstallations']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Apply subscription status filter
        if ($subscriptionStatus) {
            $query->whereHas('subscription', function ($q) use ($subscriptionStatus) {
                $q->where('status', $subscriptionStatus);
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'users_count':
                $query->orderBy('users_count', $sortDirection);
                break;
            case 'subscription_value':
                $query->leftJoin('subscriptions', 'tenants.id', '=', 'subscriptions.tenant_id')
                      ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
                      ->orderBy('plans.price', $sortDirection)
                      ->select('tenants.*');
                break;
            default:
                $query->orderBy($sortBy, $sortDirection);
        }

        $tenants = $query->paginate($perPage);

        // Enhance tenant data with additional metrics
        $tenants->getCollection()->transform(function ($tenant) {
            return $this->enhanceTenantData($tenant);
        });

        return response()->json([
            'success' => true,
            'data' => $tenants,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'subscription_status' => $subscriptionStatus,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection
            ]
        ]);
    }

    /**
     * Get detailed information for a specific tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenantData = $this->tenantManagementService->getTenantDetails($tenant);

        return response()->json([
            'success' => true,
            'data' => $tenantData
        ]);
    }

    /**
     * Get tenant health status and metrics.
     */
    public function health(Tenant $tenant): JsonResponse
    {
        $health = $this->tenantManagementService->getTenantHealth($tenant);

        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }

    /**
     * Suspend a tenant account.
     */
    public function suspend(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'notify_users' => 'boolean',
            'effective_immediately' => 'boolean'
        ]);

        $result = $this->tenantManagementService->suspendTenant(
            $tenant,
            $request->get('reason'),
            $request->user(),
            $request->boolean('notify_users', true),
            $request->boolean('effective_immediately', true)
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function reactivate(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'reason' => 'string|max:500',
            'notify_users' => 'boolean'
        ]);

        $result = $this->tenantManagementService->reactivateTenant(
            $tenant,
            $request->user(),
            $request->get('reason'),
            $request->boolean('notify_users', true)
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Start tenant impersonation session.
     */
    public function startImpersonation(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'reason' => 'required|string|max:500'
        ]);

        $result = $this->tenantManagementService->startImpersonation(
            $tenant,
            $request->user(),
            $request->get('user_id'),
            $request->get('reason')
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * End tenant impersonation session.
     */
    public function endImpersonation(Request $request): JsonResponse
    {
        $result = $this->tenantManagementService->endImpersonation($request->user());

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }

    /**
     * Get tenant billing overview.
     */
    public function billing(Tenant $tenant): JsonResponse
    {
        $billing = $this->tenantManagementService->getTenantBilling($tenant);

        return response()->json([
            'success' => true,
            'data' => $billing
        ]);
    }

    /**
     * Get tenant resource usage metrics.
     */
    public function resources(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'period' => 'in:hour,day,week,month',
            'metric_type' => 'in:storage,bandwidth,api_calls,users,modules'
        ]);

        $resources = $this->tenantManagementService->getTenantResources(
            $tenant,
            $request->get('period', 'day'),
            $request->get('metric_type')
        );

        return response()->json([
            'success' => true,
            'data' => $resources
        ]);
    }

    /**
     * Send communication to tenant users.
     */
    public function communicate(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:email,notification,announcement',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'target_users' => 'in:all,admins,active,specific',
            'user_ids' => 'array|exists:users,id',
            'priority' => 'in:low,medium,high,urgent',
            'schedule_at' => 'nullable|date|after:now'
        ]);

        $result = $this->tenantManagementService->sendCommunication(
            $tenant,
            $request->user(),
            $request->only(['type', 'subject', 'message', 'target_users', 'user_ids', 'priority', 'schedule_at'])
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Create new tenant (onboarding).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'contact_email' => 'required|email|max:255',
            'contact_name' => 'required|string|max:255',
            'plan_id' => 'required|exists:plans,id',
            'admin_user' => 'required|array',
            'admin_user.name' => 'required|string|max:255',
            'admin_user.email' => 'required|email|max:255|unique:users,email',
            'admin_user.password' => 'required|string|min:8',
            'settings' => 'array'
        ]);

        $result = $this->tenantManagementService->createTenant(
            $request->only(['name', 'domain', 'contact_email', 'contact_name', 'plan_id', 'settings']),
            $request->get('admin_user'),
            $request->user()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 201 : 422);
    }

    /**
     * Update tenant settings.
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'contact_email' => 'email|max:255',
            'contact_name' => 'string|max:255',
            'settings' => 'array',
            'limits' => 'array',
            'status' => 'in:active,suspended,pending'
        ]);

        $result = $this->tenantManagementService->updateTenant(
            $tenant,
            $request->only(['name', 'contact_email', 'contact_name', 'settings', 'limits', 'status']),
            $request->user()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Generate tenant analytics export.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|in:csv,excel,json',
            'include_metrics' => 'boolean',
            'include_billing' => 'boolean',
            'include_users' => 'boolean',
            'tenant_ids' => 'array|exists:tenants,id'
        ]);

        $result = $this->tenantManagementService->exportTenantData(
            $request->get('format'),
            $request->only(['include_metrics', 'include_billing', 'include_users', 'tenant_ids']),
            $request->user()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Generate dashboard data.
     */
    private function generateDashboardData(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $newTenantsThisMonth = Tenant::whereMonth('created_at', now()->month)->count();

        $totalUsers = User::count();
        $activeUsers = User::where('updated_at', '>=', now()->subDays(30))->count();

        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $monthlyRevenue = Subscription::where('status', 'active')
                                    ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                                    ->sum('plans.price');

        $totalModules = ModuleInstallation::count();
        $systemHealth = $this->calculateSystemHealth();

        return [
            'overview' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'suspended_tenants' => $suspendedTenants,
                'new_tenants_this_month' => $newTenantsThisMonth,
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'total_subscriptions' => $totalSubscriptions,
                'active_subscriptions' => $activeSubscriptions,
                'monthly_revenue' => $monthlyRevenue / 100, // Convert to euros
                'total_module_installations' => $totalModules
            ],
            'health' => $systemHealth,
            'trends' => $this->getTenantTrends(),
            'alerts' => $this->getSystemAlerts(),
            'top_tenants' => $this->getTopTenants(),
            'recent_activity' => $this->getRecentActivity()
        ];
    }

    /**
     * Enhance tenant data with additional metrics.
     */
    private function enhanceTenantData(Tenant $tenant): array
    {
        $data = $tenant->toArray();

        // Add health score
        $data['health_score'] = $this->tenantManagementService->calculateHealthScore($tenant);

        // Add monthly revenue
        if ($tenant->subscription) {
            $data['monthly_revenue'] = $tenant->subscription->plan ? 
                $tenant->subscription->plan->price / 100 : 0;
        } else {
            $data['monthly_revenue'] = 0;
        }

        // Add last activity
        $data['last_user_activity'] = $tenant->users()
                                           ->orderBy('updated_at', 'desc')
                                           ->value('updated_at');

        // Add storage usage
        $data['storage_usage'] = AnalyticsMetric::where('tenant_id', $tenant->id)
                                              ->where('metric_name', 'storage_usage')
                                              ->orderBy('recorded_at', 'desc')
                                              ->value('value') ?? 0;

        return $data;
    }

    /**
     * Calculate overall system health.
     */
    private function calculateSystemHealth(): array
    {
        $healthyTenants = Tenant::where('status', 'active')->count();
        $totalTenants = Tenant::count();
        $healthPercentage = $totalTenants > 0 ? ($healthyTenants / $totalTenants) * 100 : 100;

        $status = 'excellent';
        if ($healthPercentage < 95) $status = 'good';
        if ($healthPercentage < 85) $status = 'warning';
        if ($healthPercentage < 75) $status = 'critical';

        return [
            'overall_status' => $status,
            'health_percentage' => round($healthPercentage, 1),
            'healthy_tenants' => $healthyTenants,
            'total_tenants' => $totalTenants,
            'last_check' => now()->toISOString()
        ];
    }

    /**
     * Get tenant growth trends.
     */
    private function getTenantTrends(): array
    {
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Tenant::whereDate('created_at', $date)->count();
            $last7Days[] = [
                'date' => $date->toDateString(),
                'new_tenants' => $count
            ];
        }

        return [
            'new_tenants_last_7_days' => $last7Days,
            'growth_rate' => $this->calculateGrowthRate()
        ];
    }

    /**
     * Get system alerts.
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Check for suspended tenants
        $suspendedCount = Tenant::where('status', 'suspended')->count();
        if ($suspendedCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$suspendedCount} tenant(s) are currently suspended",
                'action' => 'review_suspended_tenants'
            ];
        }

        // Check for failed payments
        $failedPayments = Subscription::where('status', 'past_due')->count();
        if ($failedPayments > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "{$failedPayments} subscription(s) have failed payments",
                'action' => 'review_payment_issues'
            ];
        }

        // Check for high resource usage
        $highUsageTenants = $this->getHighResourceUsageTenants();
        if (count($highUsageTenants) > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => count($highUsageTenants) . " tenant(s) approaching resource limits",
                'action' => 'review_resource_usage'
            ];
        }

        return $alerts;
    }

    /**
     * Get top performing tenants.
     */
    private function getTopTenants(): array
    {
        return Tenant::select('tenants.*')
                    ->join('subscriptions', 'tenants.id', '=', 'subscriptions.tenant_id')
                    ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                    ->where('tenants.status', 'active')
                    ->orderBy('plans.price', 'desc')
                    ->with(['subscription.plan'])
                    ->limit(5)
                    ->get()
                    ->map(function ($tenant) {
                        return [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'monthly_value' => $tenant->subscription->plan->price / 100,
                            'users_count' => $tenant->users()->count(),
                            'health_score' => $this->tenantManagementService->calculateHealthScore($tenant)
                        ];
                    })
                    ->toArray();
    }

    /**
     * Get recent system activity.
     */
    private function getRecentActivity(): array
    {
        return [
            'recent_registrations' => Tenant::latest()->limit(5)->get(['id', 'name', 'created_at']),
            'recent_suspensions' => Tenant::where('status', 'suspended')
                                         ->latest('updated_at')
                                         ->limit(3)
                                         ->get(['id', 'name', 'updated_at']),
            'recent_reactivations' => Tenant::where('status', 'active')
                                           ->where('updated_at', '>=', now()->subDays(7))
                                           ->latest('updated_at')
                                           ->limit(3)
                                           ->get(['id', 'name', 'updated_at'])
        ];
    }

    /**
     * Calculate growth rate.
     */
    private function calculateGrowthRate(): float
    {
        $thisMonth = Tenant::whereMonth('created_at', now()->month)->count();
        $lastMonth = Tenant::whereMonth('created_at', now()->subMonth()->month)->count();

        if ($lastMonth == 0) return $thisMonth > 0 ? 100 : 0;
        
        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    /**
     * Get tenants with high resource usage.
     */
    private function getHighResourceUsageTenants(): array
    {
        return Tenant::whereHas('analyticsMetrics', function ($query) {
                      $query->where('metric_name', 'storage_usage')
                            ->where('value', '>', 80); // 80% threshold
                  })
                  ->limit(10)
                  ->get(['id', 'name'])
                  ->toArray();
    }
}