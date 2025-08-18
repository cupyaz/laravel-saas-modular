<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantAuditLog;
use App\Services\TenantSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function __construct(
        private TenantSecurityService $tenantSecurityService
    ) {}

    /**
     * Get tenant information
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'is_active' => $tenant->is_active,
                'data_residency' => $tenant->data_residency,
                'compliance_flags' => $tenant->compliance_flags,
                'isolation_level' => $tenant->isolation_level,
                'audit_enabled' => $tenant->audit_enabled,
                'current_plan' => $tenant->currentPlan()?->only(['name', 'features']),
                'resource_usage' => $this->getTenantResourceUsage($tenant),
                'security_status' => $this->getTenantSecurityStatus($tenant),
            ]
        ]);
    }

    /**
     * Update tenant settings
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255|unique:tenants,domain,' . $tenant->id,
            'data_residency' => ['sometimes', Rule::in([
                Tenant::RESIDENCY_EU,
                Tenant::RESIDENCY_US,
                Tenant::RESIDENCY_CANADA,
                Tenant::RESIDENCY_AUSTRALIA,
                Tenant::RESIDENCY_ASIA,
            ])],
            'compliance_flags' => 'sometimes|array',
            'compliance_flags.*' => Rule::in([
                Tenant::COMPLIANCE_GDPR,
                Tenant::COMPLIANCE_HIPAA,
                Tenant::COMPLIANCE_SOC2,
                Tenant::COMPLIANCE_ISO27001,
                Tenant::COMPLIANCE_PCI_DSS,
            ]),
            'security_settings' => 'sometimes|array',
            'security_settings.enforce_2fa' => 'sometimes|boolean',
            'security_settings.session_timeout' => 'sometimes|integer|min:300|max:86400',
            'security_settings.max_login_attempts' => 'sometimes|integer|min:3|max:10',
            'security_settings.password_expiry_days' => 'sometimes|integer|min:30|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $oldValues = $tenant->only(array_keys($validator->validated()));
        
        $tenant->update($validator->validated());

        // Log the update
        TenantAuditLog::logActivity(
            tenantId: $tenant->id,
            userId: auth()->id(),
            action: TenantAuditLog::ACTION_UPDATE,
            resourceType: 'tenant',
            resourceId: $tenant->id,
            oldValues: $oldValues,
            newValues: $validator->validated(),
            riskLevel: TenantAuditLog::RISK_MEDIUM,
            complianceRelevant: true
        );

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant->fresh()
        ]);
    }

    /**
     * Get tenant security status
     */
    public function securityStatus(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        $securityStatus = $this->getTenantSecurityStatus($tenant);
        $integrityCheck = $this->tenantSecurityService->validateDataIntegrity($tenant);
        $anomalies = TenantAuditLog::detectAnomalies($tenant->id);

        return response()->json([
            'security_status' => $securityStatus,
            'integrity_check' => $integrityCheck,
            'anomalies' => $anomalies,
            'compliance_status' => $this->getComplianceStatus($tenant),
            'last_security_scan' => now()->toISOString(),
        ]);
    }

    /**
     * Get tenant audit logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'action' => 'string',
            'risk_level' => Rule::in([
                TenantAuditLog::RISK_LOW,
                TenantAuditLog::RISK_MEDIUM,
                TenantAuditLog::RISK_HIGH,
                TenantAuditLog::RISK_CRITICAL,
            ]),
            'user_id' => 'integer',
            'from_date' => 'date',
            'to_date' => 'date|after:from_date',
            'compliance_only' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $query = TenantAuditLog::where('tenant_id', $tenant->id);

        // Apply filters
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('risk_level')) {
            $query->where('risk_level', $request->input('risk_level'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        if ($request->boolean('compliance_only')) {
            $query->where('compliance_relevant', true);
        }

        $perPage = $request->input('per_page', 20);
        $logs = $query->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Log audit access
        TenantAuditLog::logActivity(
            tenantId: $tenant->id,
            userId: auth()->id(),
            action: TenantAuditLog::ACTION_READ,
            resourceType: 'audit_logs',
            metadata: [
                'filters' => $request->only(['action', 'risk_level', 'user_id', 'from_date', 'to_date']),
                'page' => $request->input('page', 1),
                'per_page' => $perPage,
            ]
        );

        return response()->json($logs);
    }

    /**
     * Get audit summary
     */
    public function auditSummary(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        $days = $request->input('days', 30);
        
        if ($days < 1 || $days > 365) {
            return response()->json([
                'error' => 'Days must be between 1 and 365'
            ], 422);
        }

        $summary = TenantAuditLog::getAuditSummary($tenant->id, $days);

        return response()->json([
            'period' => [
                'days' => $days,
                'from' => now()->subDays($days)->toDateString(),
                'to' => now()->toDateString(),
            ],
            'summary' => $summary,
        ]);
    }

    /**
     * Export tenant data
     */
    public function exportData(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        try {
            $backupPath = $this->tenantSecurityService->backupTenantData($tenant);
            
            // Log data export
            TenantAuditLog::logActivity(
                tenantId: $tenant->id,
                userId: auth()->id(),
                action: TenantAuditLog::ACTION_EXPORT,
                resourceType: 'tenant_data',
                metadata: [
                    'backup_file' => basename($backupPath),
                    'export_type' => 'full_backup',
                ],
                riskLevel: TenantAuditLog::RISK_HIGH,
                complianceRelevant: true
            );

            return response()->json([
                'message' => 'Data export initiated successfully',
                'backup_file' => basename($backupPath),
                'download_url' => route('tenant.download-backup', ['file' => basename($backupPath)]),
                'expires_at' => now()->addHours(24)->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rotate tenant encryption key
     */
    public function rotateEncryptionKey(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        
        if (!$tenant) {
            return response()->json([
                'error' => 'No tenant context found'
            ], 400);
        }

        try {
            $this->tenantSecurityService->rotateEncryptionKey($tenant);

            return response()->json([
                'message' => 'Encryption key rotated successfully',
                'rotated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Key rotation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tenant resource usage
     */
    private function getTenantResourceUsage(Tenant $tenant): array
    {
        $limits = $tenant->resource_limits ?? [];
        
        return [
            'users' => [
                'current' => $tenant->users()->count(),
                'limit' => $limits['max_users'] ?? null,
                'percentage' => $this->calculateUsagePercentage(
                    $tenant->users()->count(),
                    $limits['max_users'] ?? null
                ),
            ],
            'storage' => [
                'current_gb' => 0, // This would calculate actual storage usage
                'limit_gb' => $limits['max_storage_gb'] ?? null,
                'percentage' => 0,
            ],
            'api_calls' => [
                'current_hour' => $this->getCurrentHourApiCalls($tenant),
                'limit_hour' => $limits['max_api_calls_per_hour'] ?? null,
                'percentage' => $this->calculateUsagePercentage(
                    $this->getCurrentHourApiCalls($tenant),
                    $limits['max_api_calls_per_hour'] ?? null
                ),
            ],
        ];
    }

    /**
     * Get tenant security status
     */
    private function getTenantSecurityStatus(Tenant $tenant): array
    {
        return [
            'encryption_at_rest' => $tenant->hasEncryptionAtRest(),
            '2fa_enforced' => $tenant->requires2FA(),
            'audit_enabled' => $tenant->isAuditEnabled(),
            'session_timeout' => $tenant->getSessionTimeout(),
            'isolation_level' => $tenant->isolation_level,
            'compliance_flags' => $tenant->compliance_flags,
            'data_residency' => $tenant->data_residency,
            'last_security_scan' => now()->toISOString(),
        ];
    }

    /**
     * Get compliance status
     */
    private function getComplianceStatus(Tenant $tenant): array
    {
        $status = [];
        
        foreach ($tenant->compliance_flags ?? [] as $compliance) {
            $status[$compliance] = $this->checkComplianceStatus($tenant, $compliance);
        }
        
        return $status;
    }

    /**
     * Check specific compliance status
     */
    private function checkComplianceStatus(Tenant $tenant, string $compliance): array
    {
        $issues = [];
        
        switch ($compliance) {
            case Tenant::COMPLIANCE_GDPR:
                if (!$tenant->isAuditEnabled()) {
                    $issues[] = 'Audit logging must be enabled for GDPR compliance';
                }
                break;
                
            case Tenant::COMPLIANCE_HIPAA:
                if (!$tenant->hasEncryptionAtRest()) {
                    $issues[] = 'Encryption at rest is required for HIPAA compliance';
                }
                if (!$tenant->requires2FA()) {
                    $issues[] = '2FA should be enforced for HIPAA compliance';
                }
                break;
                
            case Tenant::COMPLIANCE_SOC2:
                if (!$tenant->isAuditEnabled()) {
                    $issues[] = 'Comprehensive audit logging required for SOC2';
                }
                break;
        }
        
        return [
            'compliant' => empty($issues),
            'issues' => $issues,
            'last_check' => now()->toISOString(),
        ];
    }

    /**
     * Calculate usage percentage
     */
    private function calculateUsagePercentage(?int $current, ?int $limit): ?float
    {
        if (!$limit || $limit === 0) {
            return null;
        }
        
        return round(($current / $limit) * 100, 2);
    }

    /**
     * Get current hour API calls
     */
    private function getCurrentHourApiCalls(Tenant $tenant): int
    {
        $key = "api_calls:tenant:{$tenant->id}:" . now()->format('Y-m-d-H');
        return cache()->get($key, 0);
    }
}