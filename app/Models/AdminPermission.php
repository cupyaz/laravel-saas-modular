<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminPermission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'is_dangerous',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dangerous' => 'boolean',
        ];
    }

    /**
     * Permission categories
     */
    const CATEGORY_USER_MANAGEMENT = 'user_management';
    const CATEGORY_ROLE_MANAGEMENT = 'role_management';
    const CATEGORY_TENANT_MANAGEMENT = 'tenant_management';
    const CATEGORY_ANALYTICS = 'analytics';
    const CATEGORY_AUDIT_LOGS = 'audit_logs';
    const CATEGORY_SYSTEM = 'system_administration';
    const CATEGORY_SUPPORT = 'support';
    const CATEGORY_BULK = 'bulk_operations';
    const CATEGORY_API = 'api_management';
    const CATEGORY_SECURITY = 'security';

    /**
     * Boot the model to auto-generate slug.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($permission) {
            if (empty($permission->slug)) {
                $permission->slug = Str::slug($permission->name, '.');
            }
        });

        static::updating(function ($permission) {
            if ($permission->isDirty('name') && !$permission->isDirty('slug')) {
                $permission->slug = Str::slug($permission->name, '.');
            }
        });
    }

    /**
     * Scope for specific category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for dangerous permissions.
     */
    public function scopeDangerous($query)
    {
        return $query->where('is_dangerous', true);
    }

    /**
     * Scope for safe permissions.
     */
    public function scopeSafe($query)
    {
        return $query->where('is_dangerous', false);
    }

    /**
     * Get all permissions grouped by category.
     */
    public static function getAllGroupedByCategory(): array
    {
        return self::orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * Get default permissions to seed.
     */
    public static function getDefaultPermissions(): array
    {
        return [
            // User Management
            [
                'name' => 'View Users',
                'slug' => 'users.view',
                'description' => 'View user list and user details',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Create Users',
                'slug' => 'users.create',
                'description' => 'Create new user accounts',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Update Users',
                'slug' => 'users.update',
                'description' => 'Edit user information and settings',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Delete Users',
                'slug' => 'users.delete',
                'description' => 'Permanently delete user accounts',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Suspend Users',
                'slug' => 'users.suspend',
                'description' => 'Suspend user accounts',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Reactivate Users',
                'slug' => 'users.reactivate',
                'description' => 'Reactivate suspended user accounts',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Impersonate Users',
                'slug' => 'users.impersonate',
                'description' => 'Login as another user for support purposes',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Reset User Passwords',
                'slug' => 'users.reset_password',
                'description' => 'Reset user passwords',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Export Users',
                'slug' => 'users.export',
                'description' => 'Export user data',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Import Users',
                'slug' => 'users.import',
                'description' => 'Import user data',
                'category' => self::CATEGORY_USER_MANAGEMENT,
                'is_dangerous' => false,
            ],

            // Role Management
            [
                'name' => 'View Roles',
                'slug' => 'roles.view',
                'description' => 'View roles and permissions',
                'category' => self::CATEGORY_ROLE_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Create Roles',
                'slug' => 'roles.create',
                'description' => 'Create new roles',
                'category' => self::CATEGORY_ROLE_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Update Roles',
                'slug' => 'roles.update',
                'description' => 'Edit roles and their permissions',
                'category' => self::CATEGORY_ROLE_MANAGEMENT,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Delete Roles',
                'slug' => 'roles.delete',
                'description' => 'Delete roles',
                'category' => self::CATEGORY_ROLE_MANAGEMENT,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Assign Roles',
                'slug' => 'roles.assign',
                'description' => 'Assign roles to users',
                'category' => self::CATEGORY_ROLE_MANAGEMENT,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Revoke Roles',
                'slug' => 'roles.revoke',
                'description' => 'Remove roles from users',
                'category' => self::CATEGORY_ROLE_MANAGEMENT,
                'is_dangerous' => true,
            ],

            // Tenant Management
            [
                'name' => 'View Tenants',
                'slug' => 'tenants.view',
                'description' => 'View tenant information',
                'category' => self::CATEGORY_TENANT_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Create Tenants',
                'slug' => 'tenants.create',
                'description' => 'Create new tenants',
                'category' => self::CATEGORY_TENANT_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Update Tenants',
                'slug' => 'tenants.update',
                'description' => 'Edit tenant settings',
                'category' => self::CATEGORY_TENANT_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Delete Tenants',
                'slug' => 'tenants.delete',
                'description' => 'Delete tenants and all their data',
                'category' => self::CATEGORY_TENANT_MANAGEMENT,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Suspend Tenants',
                'slug' => 'tenants.suspend',
                'description' => 'Suspend tenant access',
                'category' => self::CATEGORY_TENANT_MANAGEMENT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Configure Tenants',
                'slug' => 'tenants.configure',
                'description' => 'Modify tenant configurations',
                'category' => self::CATEGORY_TENANT_MANAGEMENT,
                'is_dangerous' => false,
            ],

            // Analytics & Reporting
            [
                'name' => 'View Analytics',
                'slug' => 'analytics.view',
                'description' => 'Access analytics dashboards and data',
                'category' => self::CATEGORY_ANALYTICS,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Export Analytics',
                'slug' => 'analytics.export',
                'description' => 'Export analytics data',
                'category' => self::CATEGORY_ANALYTICS,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Generate Reports',
                'slug' => 'reports.generate',
                'description' => 'Generate custom reports',
                'category' => self::CATEGORY_ANALYTICS,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Schedule Reports',
                'slug' => 'reports.schedule',
                'description' => 'Schedule automated reports',
                'category' => self::CATEGORY_ANALYTICS,
                'is_dangerous' => false,
            ],

            // Audit Logs
            [
                'name' => 'View Audit Logs',
                'slug' => 'audit_logs.view',
                'description' => 'View system audit logs',
                'category' => self::CATEGORY_AUDIT_LOGS,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Export Audit Logs',
                'slug' => 'audit_logs.export',
                'description' => 'Export audit log data',
                'category' => self::CATEGORY_AUDIT_LOGS,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Delete Audit Logs',
                'slug' => 'audit_logs.delete',
                'description' => 'Delete old audit logs',
                'category' => self::CATEGORY_AUDIT_LOGS,
                'is_dangerous' => true,
            ],

            // System Administration
            [
                'name' => 'Configure System',
                'slug' => 'system.configure',
                'description' => 'Modify system configuration',
                'category' => self::CATEGORY_SYSTEM,
                'is_dangerous' => true,
            ],
            [
                'name' => 'System Maintenance',
                'slug' => 'system.maintenance',
                'description' => 'Perform system maintenance tasks',
                'category' => self::CATEGORY_SYSTEM,
                'is_dangerous' => true,
            ],
            [
                'name' => 'System Backup',
                'slug' => 'system.backup',
                'description' => 'Create system backups',
                'category' => self::CATEGORY_SYSTEM,
                'is_dangerous' => false,
            ],
            [
                'name' => 'System Restore',
                'slug' => 'system.restore',
                'description' => 'Restore from system backups',
                'category' => self::CATEGORY_SYSTEM,
                'is_dangerous' => true,
            ],

            // Support Operations
            [
                'name' => 'Support Access',
                'slug' => 'support.access',
                'description' => 'Access support tools and features',
                'category' => self::CATEGORY_SUPPORT,
                'is_dangerous' => false,
            ],
            [
                'name' => 'Escalate Support',
                'slug' => 'support.escalate',
                'description' => 'Escalate support tickets',
                'category' => self::CATEGORY_SUPPORT,
                'is_dangerous' => false,
            ],

            // Bulk Operations
            [
                'name' => 'Bulk User Operations',
                'slug' => 'bulk.users',
                'description' => 'Perform bulk operations on users',
                'category' => self::CATEGORY_BULK,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Bulk Tenant Operations',
                'slug' => 'bulk.tenants',
                'description' => 'Perform bulk operations on tenants',
                'category' => self::CATEGORY_BULK,
                'is_dangerous' => true,
            ],

            // Security Management
            [
                'name' => 'Configure Security',
                'slug' => 'security.configure',
                'description' => 'Configure security settings',
                'category' => self::CATEGORY_SECURITY,
                'is_dangerous' => true,
            ],
            [
                'name' => 'Monitor Security',
                'slug' => 'security.monitor',
                'description' => 'Monitor security events',
                'category' => self::CATEGORY_SECURITY,
                'is_dangerous' => false,
            ],
        ];
    }

    /**
     * Get dangerous permissions.
     */
    public static function getDangerousPermissions(): array
    {
        return self::where('is_dangerous', true)->pluck('slug')->toArray();
    }

    /**
     * Check if a permission is dangerous.
     */
    public static function isDangerous(string $permission): bool
    {
        return self::where('slug', $permission)->where('is_dangerous', true)->exists();
    }
}