<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_user_id',
        'target_type',
        'target_id',
        'action',
        'description',
        'severity',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
        'old_values',
        'new_values',
        'additional_data',
        'tenant_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'additional_data' => 'array',
        ];
    }

    /**
     * Action constants
     */
    const ACTION_USER_CREATED = 'user_created';
    const ACTION_USER_UPDATED = 'user_updated';
    const ACTION_USER_SUSPENDED = 'user_suspended';
    const ACTION_USER_REACTIVATED = 'user_reactivated';
    const ACTION_USER_DELETED = 'user_deleted';
    const ACTION_USER_PASSWORD_RESET = 'user_password_reset';
    const ACTION_USER_ROLE_ASSIGNED = 'user_role_assigned';
    const ACTION_USER_ROLE_REMOVED = 'user_role_removed';
    const ACTION_USER_IMPERSONATED = 'user_impersonated';
    const ACTION_BULK_OPERATION = 'bulk_operation';
    const ACTION_TENANT_CREATED = 'tenant_created';
    const ACTION_TENANT_UPDATED = 'tenant_updated';
    const ACTION_TENANT_SUSPENDED = 'tenant_suspended';
    const ACTION_SYSTEM_CONFIG_CHANGED = 'system_config_changed';
    const ACTION_LOGIN = 'admin_login';
    const ACTION_LOGOUT = 'admin_logout';
    const ACTION_FAILED_LOGIN = 'admin_failed_login';

    /**
     * Severity constants
     */
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Get the admin user who performed the action.
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get the target of the action (polymorphic).
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the associated tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope for specific actions.
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific severity.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope for specific admin user.
     */
    public function scopeByAdmin($query, int $adminUserId)
    {
        return $query->where('admin_user_id', $adminUserId);
    }

    /**
     * Scope for recent logs.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for critical logs.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    /**
     * Log an admin action.
     */
    public static function logAction(
        int $adminUserId,
        string $action,
        string $description,
        ?Model $target = null,
        array $oldValues = [],
        array $newValues = [],
        array $additionalData = [],
        string $severity = self::SEVERITY_INFO,
        ?int $tenantId = null
    ): self {
        return self::create([
            'admin_user_id' => $adminUserId,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->id,
            'action' => $action,
            'description' => $description,
            'severity' => $severity,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_method' => request()->method(),
            'request_url' => request()->fullUrl(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'additional_data' => $additionalData,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Log user creation.
     */
    public static function logUserCreated(int $adminUserId, User $user): self
    {
        return self::logAction(
            $adminUserId,
            self::ACTION_USER_CREATED,
            "User '{$user->email}' created by admin",
            $user,
            [],
            $user->toArray(),
            ['user_id' => $user->id],
            self::SEVERITY_INFO
        );
    }

    /**
     * Log user update.
     */
    public static function logUserUpdated(int $adminUserId, User $user, array $oldValues, array $newValues): self
    {
        return self::logAction(
            $adminUserId,
            self::ACTION_USER_UPDATED,
            "User '{$user->email}' updated by admin",
            $user,
            $oldValues,
            $newValues,
            ['user_id' => $user->id],
            self::SEVERITY_INFO
        );
    }

    /**
     * Log user suspension.
     */
    public static function logUserSuspended(int $adminUserId, User $user, string $reason): self
    {
        return self::logAction(
            $adminUserId,
            self::ACTION_USER_SUSPENDED,
            "User '{$user->email}' suspended by admin",
            $user,
            ['suspended_at' => null],
            ['suspended_at' => now(), 'suspension_reason' => $reason],
            ['user_id' => $user->id, 'reason' => $reason],
            self::SEVERITY_WARNING
        );
    }

    /**
     * Log user impersonation.
     */
    public static function logUserImpersonated(int $adminUserId, User $user, string $reason): self
    {
        return self::logAction(
            $adminUserId,
            self::ACTION_USER_IMPERSONATED,
            "User '{$user->email}' impersonated by admin",
            $user,
            [],
            [],
            ['user_id' => $user->id, 'reason' => $reason],
            self::SEVERITY_WARNING
        );
    }

    /**
     * Log bulk operation.
     */
    public static function logBulkOperation(
        int $adminUserId,
        string $operationType,
        string $targetModel,
        int $totalRecords,
        int $successfulRecords,
        int $failedRecords
    ): self {
        return self::logAction(
            $adminUserId,
            self::ACTION_BULK_OPERATION,
            "Bulk {$operationType} performed on {$totalRecords} {$targetModel} records",
            null,
            [],
            [],
            [
                'operation_type' => $operationType,
                'target_model' => $targetModel,
                'total_records' => $totalRecords,
                'successful_records' => $successfulRecords,
                'failed_records' => $failedRecords,
            ],
            $failedRecords > 0 ? self::SEVERITY_WARNING : self::SEVERITY_INFO
        );
    }

    /**
     * Log admin login.
     */
    public static function logAdminLogin(int $adminUserId): self
    {
        return self::logAction(
            $adminUserId,
            self::ACTION_LOGIN,
            'Admin user logged in',
            null,
            [],
            [],
            ['login_at' => now()],
            self::SEVERITY_INFO
        );
    }

    /**
     * Log failed admin login.
     */
    public static function logFailedAdminLogin(string $email, string $reason): self
    {
        return self::create([
            'admin_user_id' => 0, // Special case for failed logins
            'target_type' => null,
            'target_id' => null,
            'action' => self::ACTION_FAILED_LOGIN,
            'description' => "Failed admin login attempt for email: {$email}",
            'severity' => self::SEVERITY_WARNING,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_method' => request()->method(),
            'request_url' => request()->fullUrl(),
            'old_values' => [],
            'new_values' => [],
            'additional_data' => ['email' => $email, 'reason' => $reason],
            'tenant_id' => null,
        ]);
    }

    /**
     * Get human-readable action name.
     */
    public function getActionNameAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_USER_CREATED => 'User Created',
            self::ACTION_USER_UPDATED => 'User Updated',
            self::ACTION_USER_SUSPENDED => 'User Suspended',
            self::ACTION_USER_REACTIVATED => 'User Reactivated',
            self::ACTION_USER_DELETED => 'User Deleted',
            self::ACTION_USER_PASSWORD_RESET => 'Password Reset',
            self::ACTION_USER_ROLE_ASSIGNED => 'Role Assigned',
            self::ACTION_USER_ROLE_REMOVED => 'Role Removed',
            self::ACTION_USER_IMPERSONATED => 'User Impersonated',
            self::ACTION_BULK_OPERATION => 'Bulk Operation',
            self::ACTION_TENANT_CREATED => 'Tenant Created',
            self::ACTION_TENANT_UPDATED => 'Tenant Updated',
            self::ACTION_TENANT_SUSPENDED => 'Tenant Suspended',
            self::ACTION_SYSTEM_CONFIG_CHANGED => 'System Config Changed',
            self::ACTION_LOGIN => 'Admin Login',
            self::ACTION_LOGOUT => 'Admin Logout',
            self::ACTION_FAILED_LOGIN => 'Failed Login',
            default => ucwords(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Get severity badge color.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_INFO => 'blue',
            self::SEVERITY_WARNING => 'yellow',
            self::SEVERITY_CRITICAL => 'red',
            default => 'gray',
        };
    }
}