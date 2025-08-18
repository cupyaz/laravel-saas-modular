<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
        'risk_level',
        'compliance_relevant',
        'session_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'compliance_relevant' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Risk levels
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    // Action types
    const ACTION_CREATE = 'create';
    const ACTION_READ = 'read';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    const ACTION_PERMISSION_CHANGE = 'permission_change';
    const ACTION_SECURITY_EVENT = 'security_event';

    /**
     * Get the tenant that owns the audit log
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for high-risk activities
     */
    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    /**
     * Scope for compliance-relevant logs
     */
    public function scopeComplianceRelevant($query)
    {
        return $query->where('compliance_relevant', true);
    }

    /**
     * Scope for specific action types
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for security events
     */
    public function scopeSecurityEvents($query)
    {
        return $query->where('action', self::ACTION_SECURITY_EVENT);
    }

    /**
     * Scope for logs within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for logs by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for logs by IP address
     */
    public function scopeByIpAddress($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Static method to log tenant activity
     */
    public static function logActivity(
        int $tenantId,
        ?int $userId,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $riskLevel = self::RISK_LOW,
        bool $complianceRelevant = false,
        ?array $metadata = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
            'risk_level' => $riskLevel,
            'compliance_relevant' => $complianceRelevant,
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent(
        int $tenantId,
        ?int $userId,
        string $eventType,
        array $details = [],
        string $riskLevel = self::RISK_MEDIUM
    ): self {
        return self::logActivity(
            tenantId: $tenantId,
            userId: $userId,
            action: self::ACTION_SECURITY_EVENT,
            resourceType: 'security',
            metadata: array_merge(['event_type' => $eventType], $details),
            riskLevel: $riskLevel,
            complianceRelevant: true
        );
    }

    /**
     * Log data access
     */
    public static function logDataAccess(
        int $tenantId,
        int $userId,
        string $resourceType,
        int $resourceId,
        string $action = self::ACTION_READ,
        ?array $metadata = null
    ): self {
        return self::logActivity(
            tenantId: $tenantId,
            userId: $userId,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            metadata: $metadata,
            complianceRelevant: true
        );
    }

    /**
     * Log permission changes
     */
    public static function logPermissionChange(
        int $tenantId,
        int $userId,
        int $targetUserId,
        array $oldPermissions,
        array $newPermissions
    ): self {
        return self::logActivity(
            tenantId: $tenantId,
            userId: $userId,
            action: self::ACTION_PERMISSION_CHANGE,
            resourceType: 'user_permissions',
            resourceId: $targetUserId,
            oldValues: $oldPermissions,
            newValues: $newPermissions,
            riskLevel: self::RISK_HIGH,
            complianceRelevant: true
        );
    }

    /**
     * Get audit summary for tenant
     */
    public static function getAuditSummary(int $tenantId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $logs = self::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->get();

        return [
            'total_events' => $logs->count(),
            'high_risk_events' => $logs->whereIn('risk_level', [self::RISK_HIGH, self::RISK_CRITICAL])->count(),
            'security_events' => $logs->where('action', self::ACTION_SECURITY_EVENT)->count(),
            'compliance_events' => $logs->where('compliance_relevant', true)->count(),
            'unique_users' => $logs->pluck('user_id')->unique()->count(),
            'actions_breakdown' => $logs->groupBy('action')->map->count(),
            'risk_breakdown' => $logs->groupBy('risk_level')->map->count(),
            'daily_activity' => $logs->groupBy(fn($log) => $log->created_at->format('Y-m-d'))->map->count(),
        ];
    }

    /**
     * Detect anomalous activity
     */
    public static function detectAnomalies(int $tenantId): array
    {
        $recentLogs = self::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        $anomalies = [];

        // Check for unusual login patterns
        $loginCounts = $recentLogs->where('action', self::ACTION_LOGIN)
            ->groupBy('ip_address')
            ->map->count();

        foreach ($loginCounts as $ip => $count) {
            if ($count > 20) { // More than 20 logins from same IP
                $anomalies[] = [
                    'type' => 'suspicious_login_pattern',
                    'description' => "Unusual number of logins from IP: {$ip}",
                    'count' => $count,
                    'risk_level' => self::RISK_HIGH,
                ];
            }
        }

        // Check for off-hours activity
        $offHoursActivity = $recentLogs->filter(function ($log) {
            $hour = $log->created_at->hour;
            return $hour < 6 || $hour > 22; // Before 6 AM or after 10 PM
        });

        if ($offHoursActivity->count() > 10) {
            $anomalies[] = [
                'type' => 'off_hours_activity',
                'description' => 'Unusual activity during off-hours',
                'count' => $offHoursActivity->count(),
                'risk_level' => self::RISK_MEDIUM,
            ];
        }

        // Check for bulk operations
        $bulkOperations = $recentLogs->groupBy(['user_id', 'action'])
            ->map(function ($userActions) {
                return $userActions->map->count();
            })
            ->flatten()
            ->filter(fn($count) => $count > 100);

        if ($bulkOperations->count() > 0) {
            $anomalies[] = [
                'type' => 'bulk_operations',
                'description' => 'Unusual bulk operations detected',
                'max_count' => $bulkOperations->max(),
                'risk_level' => self::RISK_MEDIUM,
            ];
        }

        return $anomalies;
    }
}