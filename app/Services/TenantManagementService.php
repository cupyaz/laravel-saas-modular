<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\AnalyticsMetric;
use App\Models\AdminAuditLog;
use App\Models\UserImpersonationSession;
use App\Models\ModuleInstallation;
use App\Notifications\TenantSuspendedNotification;
use App\Notifications\TenantReactivatedNotification;
use App\Notifications\AdminNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantManagementService
{
    /**
     * Get detailed tenant information.
     */
    public function getTenantDetails(Tenant $tenant): array
    {
        $cacheKey = "tenant_details_{$tenant->id}_" . now()->format('Y-m-d-H');
        
        return Cache::remember($cacheKey, 3600, function () use ($tenant) {
            return [
                'basic_info' => $this->getTenantBasicInfo($tenant),
                'metrics' => $this->getTenantMetrics($tenant),
                'health' => $this->getTenantHealth($tenant),
                'billing' => $this->getTenantBilling($tenant),
                'users' => $this->getTenantUsers($tenant),
                'modules' => $this->getTenantModules($tenant),
                'activity' => $this->getTenantActivity($tenant),
                'support' => $this->getTenantSupport($tenant)
            ];
        });
    }

    /**
     * Get tenant health status.
     */
    public function getTenantHealth(Tenant $tenant): array
    {
        $healthScore = $this->calculateHealthScore($tenant);
        $issues = $this->identifyHealthIssues($tenant);
        $recommendations = $this->generateHealthRecommendations($tenant, $issues);

        return [
            'overall_score' => $healthScore,
            'status' => $this->getHealthStatus($healthScore),
            'last_check' => now()->toISOString(),
            'metrics' => [
                'uptime_percentage' => $this->getUptimePercentage($tenant),
                'error_rate' => $this->getErrorRate($tenant),
                'response_time' => $this->getAverageResponseTime($tenant),
                'user_satisfaction' => $this->getUserSatisfactionScore($tenant),
                'resource_efficiency' => $this->getResourceEfficiency($tenant)
            ],
            'issues' => $issues,
            'recommendations' => $recommendations,
            'trend' => $this->getHealthTrend($tenant)
        ];
    }

    /**
     * Suspend a tenant account.
     */
    public function suspendTenant(Tenant $tenant, string $reason, User $admin, bool $notifyUsers = true, bool $effectiveImmediately = true): array
    {
        if ($tenant->status === 'suspended') {
            return [
                'success' => false,
                'message' => 'Tenant is already suspended'
            ];
        }

        DB::beginTransaction();
        
        try {
            // Update tenant status
            $tenant->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $reason,
                'suspended_by' => $admin->id
            ]);

            // Log the action
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'tenant_suspended',
                'target_type' => 'tenant',
                'target_id' => $tenant->id,
                'details' => [
                    'reason' => $reason,
                    'effective_immediately' => $effectiveImmediately,
                    'notify_users' => $notifyUsers
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Pause active subscriptions if immediate
            if ($effectiveImmediately && $tenant->subscription) {
                $tenant->subscription->update(['status' => 'paused']);
            }

            // Record metrics
            AnalyticsMetric::record('tenant_suspended', 1, [
                'tenant_id' => $tenant->id,
                'reason' => $reason,
                'admin_id' => $admin->id
            ]);

            // Notify users if requested
            if ($notifyUsers) {
                $this->notifyTenantUsers($tenant, 'suspended', $reason);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Tenant suspended successfully',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'suspended_at' => $tenant->suspended_at->toISOString(),
                    'effective_immediately' => $effectiveImmediately
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to suspend tenant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function reactivateTenant(Tenant $tenant, User $admin, ?string $reason = null, bool $notifyUsers = true): array
    {
        if ($tenant->status !== 'suspended') {
            return [
                'success' => false,
                'message' => 'Tenant is not suspended'
            ];
        }

        DB::beginTransaction();
        
        try {
            // Update tenant status
            $tenant->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
                'suspended_by' => null,
                'reactivated_at' => now(),
                'reactivated_by' => $admin->id
            ]);

            // Log the action
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'tenant_reactivated',
                'target_type' => 'tenant',
                'target_id' => $tenant->id,
                'details' => [
                    'reason' => $reason,
                    'notify_users' => $notifyUsers
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Resume subscriptions
            if ($tenant->subscription && $tenant->subscription->status === 'paused') {
                $tenant->subscription->update(['status' => 'active']);
            }

            // Record metrics
            AnalyticsMetric::record('tenant_reactivated', 1, [
                'tenant_id' => $tenant->id,
                'reason' => $reason,
                'admin_id' => $admin->id
            ]);

            // Notify users if requested
            if ($notifyUsers) {
                $this->notifyTenantUsers($tenant, 'reactivated', $reason);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Tenant reactivated successfully',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'reactivated_at' => $tenant->reactivated_at->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to reactivate tenant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Start tenant impersonation session.
     */
    public function startImpersonation(Tenant $tenant, User $admin, ?int $userId = null, string $reason = ''): array
    {
        if ($tenant->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Cannot impersonate users in inactive tenant'
            ];
        }

        $targetUser = null;
        if ($userId) {
            $targetUser = $tenant->users()->find($userId);
            if (!$targetUser) {
                return [
                    'success' => false,
                    'message' => 'User not found in this tenant'
                ];
            }
        } else {
            // Use first admin user of the tenant
            $targetUser = $tenant->users()->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })->first();

            if (!$targetUser) {
                $targetUser = $tenant->users()->first();
            }
        }

        if (!$targetUser) {
            return [
                'success' => false,
                'message' => 'No users found in this tenant'
            ];
        }

        DB::beginTransaction();
        
        try {
            // End any existing impersonation sessions
            UserImpersonationSession::where('admin_id', $admin->id)
                                   ->where('ended_at', null)
                                   ->update(['ended_at' => now()]);

            // Create new impersonation session
            $session = UserImpersonationSession::create([
                'admin_id' => $admin->id,
                'tenant_id' => $tenant->id,
                'user_id' => $targetUser->id,
                'reason' => $reason,
                'started_at' => now(),
                'session_token' => Str::random(64),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Log the action
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'impersonation_started',
                'target_type' => 'user',
                'target_id' => $targetUser->id,
                'details' => [
                    'tenant_id' => $tenant->id,
                    'reason' => $reason,
                    'session_id' => $session->id
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Record metrics
            AnalyticsMetric::record('admin_impersonation_started', 1, [
                'tenant_id' => $tenant->id,
                'admin_id' => $admin->id,
                'user_id' => $targetUser->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Impersonation session started successfully',
                'data' => [
                    'session_id' => $session->id,
                    'session_token' => $session->session_token,
                    'tenant' => $tenant->only(['id', 'name', 'domain']),
                    'user' => $targetUser->only(['id', 'name', 'email']),
                    'started_at' => $session->started_at->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to start impersonation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * End impersonation session.
     */
    public function endImpersonation(User $admin): array
    {
        $session = UserImpersonationSession::where('admin_id', $admin->id)
                                          ->whereNull('ended_at')
                                          ->first();

        if (!$session) {
            return [
                'success' => false,
                'message' => 'No active impersonation session found'
            ];
        }

        $session->update([
            'ended_at' => now(),
            'duration_seconds' => now()->diffInSeconds($session->started_at)
        ]);

        // Log the action
        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'impersonation_ended',
            'target_type' => 'user',
            'target_id' => $session->user_id,
            'details' => [
                'session_id' => $session->id,
                'duration_seconds' => $session->duration_seconds
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        return [
            'success' => true,
            'message' => 'Impersonation session ended successfully'
        ];
    }

    /**
     * Get tenant billing information.
     */
    public function getTenantBilling(Tenant $tenant): array
    {
        $subscription = $tenant->subscription;
        
        return [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan' => $subscription->plan ? [
                    'name' => $subscription->plan->name,
                    'price' => $subscription->plan->price / 100,
                    'currency' => 'EUR',
                    'interval' => $subscription->plan->interval
                ] : null,
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start?->toISOString(),
                'current_period_end' => $subscription->current_period_end?->toISOString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'created_at' => $subscription->created_at->toISOString()
            ] : null,
            'payment_method' => $this->getTenantPaymentMethod($tenant),
            'invoices' => $this->getTenantInvoices($tenant),
            'revenue_metrics' => $this->getTenantRevenueMetrics($tenant)
        ];
    }

    /**
     * Get tenant resource usage.
     */
    public function getTenantResources(Tenant $tenant, string $period = 'day', ?string $metricType = null): array
    {
        $metrics = ['storage', 'bandwidth', 'api_calls', 'users', 'modules'];
        if ($metricType) {
            $metrics = [$metricType];
        }

        $data = [];
        foreach ($metrics as $metric) {
            $data[$metric] = $this->getResourceMetric($tenant, $metric, $period);
        }

        return [
            'tenant_id' => $tenant->id,
            'period' => $period,
            'metrics' => $data,
            'limits' => $this->getTenantLimits($tenant),
            'usage_percentage' => $this->calculateUsagePercentages($tenant, $data),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Send communication to tenant users.
     */
    public function sendCommunication(Tenant $tenant, User $admin, array $data): array
    {
        $type = $data['type'];
        $subject = $data['subject'];
        $message = $data['message'];
        $targetUsers = $data['target_users'] ?? 'all';
        $userIds = $data['user_ids'] ?? [];
        $priority = $data['priority'] ?? 'medium';
        $scheduleAt = $data['schedule_at'] ?? null;

        // Get target users based on criteria
        $users = $this->getTargetUsers($tenant, $targetUsers, $userIds);

        if ($users->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No users found matching the criteria'
            ];
        }

        try {
            foreach ($users as $user) {
                // Create notification based on type
                $notification = new AdminNotification([
                    'type' => $type,
                    'subject' => $subject,
                    'message' => $message,
                    'priority' => $priority,
                    'from_admin' => $admin->name,
                    'tenant_id' => $tenant->id
                ]);

                if ($scheduleAt) {
                    $user->notify($notification->delay(Carbon::parse($scheduleAt)));
                } else {
                    $user->notify($notification);
                }
            }

            // Log the communication
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'tenant_communication_sent',
                'target_type' => 'tenant',
                'target_id' => $tenant->id,
                'details' => [
                    'type' => $type,
                    'subject' => $subject,
                    'target_users' => $targetUsers,
                    'users_count' => $users->count(),
                    'priority' => $priority,
                    'scheduled_at' => $scheduleAt
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return [
                'success' => true,
                'message' => "Communication sent to {$users->count()} user(s)",
                'data' => [
                    'users_count' => $users->count(),
                    'scheduled_at' => $scheduleAt
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send communication: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create new tenant.
     */
    public function createTenant(array $tenantData, array $adminUser, User $createdBy): array
    {
        DB::beginTransaction();
        
        try {
            // Create tenant
            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'domain' => $tenantData['domain'],
                'contact_email' => $tenantData['contact_email'],
                'contact_name' => $tenantData['contact_name'],
                'status' => 'active',
                'settings' => $tenantData['settings'] ?? [],
                'created_by' => $createdBy->id
            ]);

            // Create admin user for tenant
            $user = User::create([
                'name' => $adminUser['name'],
                'email' => $adminUser['email'],
                'password' => Hash::make($adminUser['password']),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now()
            ]);

            // Assign admin role
            $user->assignRole('admin');

            // Create subscription
            $plan = Plan::find($tenantData['plan_id']);
            if ($plan) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'trialing',
                    'trial_ends_at' => now()->addDays(14),
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth()
                ]);
            }

            // Log the action
            AdminAuditLog::create([
                'admin_id' => $createdBy->id,
                'action' => 'tenant_created',
                'target_type' => 'tenant',
                'target_id' => $tenant->id,
                'details' => [
                    'tenant_name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'plan_id' => $tenantData['plan_id'],
                    'admin_user_email' => $adminUser['email']
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            // Record metrics
            AnalyticsMetric::record('tenant_created', 1, [
                'tenant_id' => $tenant->id,
                'plan_id' => $tenantData['plan_id'],
                'created_by' => $createdBy->id
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => [
                    'tenant' => $tenant,
                    'admin_user' => $user->only(['id', 'name', 'email']),
                    'login_url' => "https://{$tenant->domain}/login"
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update tenant settings.
     */
    public function updateTenant(Tenant $tenant, array $data, User $admin): array
    {
        try {
            $original = $tenant->toArray();
            $tenant->update($data);

            // Log the action
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'tenant_updated',
                'target_type' => 'tenant',
                'target_id' => $tenant->id,
                'details' => [
                    'changed_fields' => array_keys($data),
                    'original_values' => array_intersect_key($original, $data)
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return [
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => $tenant->fresh()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update tenant: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Export tenant data.
     */
    public function exportTenantData(string $format, array $options, User $admin): array
    {
        try {
            $tenantIds = $options['tenant_ids'] ?? [];
            $includeMetrics = $options['include_metrics'] ?? false;
            $includeBilling = $options['include_billing'] ?? false;
            $includeUsers = $options['include_users'] ?? false;

            $query = Tenant::query();
            if (!empty($tenantIds)) {
                $query->whereIn('id', $tenantIds);
            }

            $tenants = $query->get();
            $data = [];

            foreach ($tenants as $tenant) {
                $tenantData = $tenant->toArray();

                if ($includeMetrics) {
                    $tenantData['metrics'] = $this->getTenantMetrics($tenant);
                }

                if ($includeBilling) {
                    $tenantData['billing'] = $this->getTenantBilling($tenant);
                }

                if ($includeUsers) {
                    $tenantData['users'] = $tenant->users()->get()->toArray();
                }

                $data[] = $tenantData;
            }

            $filename = 'tenant_export_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            $filePath = 'exports/' . $filename;

            switch ($format) {
                case 'json':
                    Storage::put($filePath, json_encode($data, JSON_PRETTY_PRINT));
                    break;
                case 'csv':
                    $this->exportToCsv($data, $filePath);
                    break;
                case 'excel':
                    $this->exportToExcel($data, $filePath);
                    break;
            }

            // Log the export
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'tenant_data_exported',
                'target_type' => 'system',
                'target_id' => null,
                'details' => [
                    'format' => $format,
                    'tenant_count' => count($data),
                    'options' => $options,
                    'filename' => $filename
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return [
                'success' => true,
                'message' => 'Data exported successfully',
                'data' => [
                    'filename' => $filename,
                    'download_url' => Storage::url($filePath),
                    'record_count' => count($data)
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to export data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate tenant health score.
     */
    public function calculateHealthScore(Tenant $tenant): int
    {
        $score = 100;

        // Check tenant status
        if ($tenant->status !== 'active') $score -= 30;

        // Check subscription status
        if ($tenant->subscription) {
            if ($tenant->subscription->status === 'cancelled') $score -= 25;
            elseif ($tenant->subscription->status === 'past_due') $score -= 15;
            elseif ($tenant->subscription->status === 'paused') $score -= 10;
        } else {
            $score -= 20; // No subscription
        }

        // Check user activity
        $activeUsers = $tenant->users()->where('updated_at', '>=', now()->subDays(30))->count();
        $totalUsers = $tenant->users()->count();
        if ($totalUsers > 0) {
            $activityRate = ($activeUsers / $totalUsers) * 100;
            if ($activityRate < 50) $score -= 15;
            elseif ($activityRate < 80) $score -= 5;
        }

        // Check resource usage
        $storageUsage = $this->getResourceUsagePercentage($tenant, 'storage');
        if ($storageUsage > 95) $score -= 10;
        elseif ($storageUsage > 85) $score -= 5;

        // Check error rate
        $errorRate = $this->getErrorRate($tenant);
        if ($errorRate > 5) $score -= 10;
        elseif ($errorRate > 2) $score -= 5;

        return max(0, min(100, $score));
    }

    // Additional helper methods...
    
    private function getTenantBasicInfo(Tenant $tenant): array
    {
        return $tenant->only(['id', 'name', 'domain', 'contact_email', 'contact_name', 'status', 'created_at', 'updated_at']);
    }

    private function getTenantMetrics(Tenant $tenant): array
    {
        return [
            'users_count' => $tenant->users()->count(),
            'active_users_30d' => $tenant->users()->where('updated_at', '>=', now()->subDays(30))->count(),
            'modules_count' => $tenant->moduleInstallations()->count(),
            'storage_usage' => $this->getResourceUsagePercentage($tenant, 'storage'),
            'api_calls_today' => $this->getResourceMetric($tenant, 'api_calls', 'day'),
            'last_activity' => $tenant->users()->max('updated_at')
        ];
    }

    private function getTenantUsers(Tenant $tenant): array
    {
        return $tenant->users()
                     ->with('roles')
                     ->get()
                     ->map(function ($user) {
                         return [
                             'id' => $user->id,
                             'name' => $user->name,
                             'email' => $user->email,
                             'roles' => $user->roles->pluck('name'),
                             'last_activity' => $user->updated_at,
                             'status' => $user->status ?? 'active'
                         ];
                     })
                     ->toArray();
    }

    private function getTenantModules(Tenant $tenant): array
    {
        return $tenant->moduleInstallations()
                     ->with('module')
                     ->get()
                     ->map(function ($installation) {
                         return [
                             'module_name' => $installation->module->name,
                             'status' => $installation->status,
                             'installed_at' => $installation->created_at,
                             'version' => $installation->version,
                             'health_score' => $installation->getHealthScore()
                         ];
                     })
                     ->toArray();
    }

    private function getTenantActivity(Tenant $tenant): array
    {
        return [
            'recent_logins' => $tenant->users()
                                   ->where('updated_at', '>=', now()->subDays(7))
                                   ->orderBy('updated_at', 'desc')
                                   ->limit(10)
                                   ->get(['name', 'updated_at'])
                                   ->toArray(),
            'recent_installations' => $tenant->moduleInstallations()
                                           ->where('created_at', '>=', now()->subDays(30))
                                           ->with('module')
                                           ->orderBy('created_at', 'desc')
                                           ->limit(5)
                                           ->get()
                                           ->toArray()
        ];
    }

    private function getTenantSupport(Tenant $tenant): array
    {
        return [
            'open_tickets' => 0, // Would integrate with support system
            'last_communication' => null, // Would track admin communications
            'satisfaction_score' => null, // Would come from surveys
            'priority_level' => 'normal' // Based on plan or issues
        ];
    }

    // Additional helper methods would be implemented here...
    // These are placeholder implementations
    
    private function getUptimePercentage(Tenant $tenant): float { return 99.5; }
    private function getErrorRate(Tenant $tenant): float { return 0.5; }
    private function getAverageResponseTime(Tenant $tenant): float { return 250; }
    private function getUserSatisfactionScore(Tenant $tenant): float { return 4.2; }
    private function getResourceEfficiency(Tenant $tenant): float { return 85.0; }
    private function getHealthTrend(Tenant $tenant): string { return 'stable'; }
    private function getResourceUsagePercentage(Tenant $tenant, string $resource): float { return 45.0; }
    private function getResourceMetric(Tenant $tenant, string $metric, string $period): float { return 1000; }
    private function getTenantLimits(Tenant $tenant): array { return ['storage' => 1000, 'users' => 100]; }
    private function calculateUsagePercentages(Tenant $tenant, array $data): array { return ['storage' => 45]; }
    private function getTargetUsers(Tenant $tenant, string $target, array $userIds) { return $tenant->users(); }
    private function getTenantPaymentMethod(Tenant $tenant): ?array { return null; }
    private function getTenantInvoices(Tenant $tenant): array { return []; }
    private function getTenantRevenueMetrics(Tenant $tenant): array { return []; }
    private function notifyTenantUsers(Tenant $tenant, string $action, string $reason): void {}
    private function identifyHealthIssues(Tenant $tenant): array { return []; }
    private function generateHealthRecommendations(Tenant $tenant, array $issues): array { return []; }
    private function getHealthStatus(int $score): string { 
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'warning';
        return 'critical';
    }
    private function exportToCsv(array $data, string $filePath): void {}
    private function exportToExcel(array $data, string $filePath): void {}
}