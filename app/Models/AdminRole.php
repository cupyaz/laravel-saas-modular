<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class AdminRole extends Model
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
        'permissions',
        'is_system_role',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system_role' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * System role constants
     */
    const SUPER_ADMIN_ROLE = 'super_admin';
    const USER_MANAGER_ROLE = 'user_manager';
    const TENANT_MANAGER_ROLE = 'tenant_manager';
    const SUPPORT_ROLE = 'support';
    const ANALYST_ROLE = 'analyst';

    /**
     * Boot the model to auto-generate slug.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });

        static::updating(function ($role) {
            if ($role->isDirty('name') && !$role->isDirty('slug')) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    /**
     * Get the users that have this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_admin_roles')
            ->withPivot(['assigned_by', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Get active users with this role.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->where('users.is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_admin_roles.expires_at')
                    ->orWhere('user_admin_roles.expires_at', '>', now());
            });
    }

    /**
     * Scope for active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for system roles.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_role', true);
    }

    /**
     * Scope for custom roles.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system_role', false);
    }

    /**
     * Check if role has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Add permission to role.
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Remove permission from role.
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }

    /**
     * Get permission categories for this role.
     */
    public function getPermissionCategories(): array
    {
        $permissions = $this->permissions ?? [];
        $categories = [];
        
        foreach ($permissions as $permission) {
            // Extract category from permission (e.g., 'users.create' -> 'users')
            $parts = explode('.', $permission);
            if (count($parts) >= 2) {
                $category = $parts[0];
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }
        }
        
        return $categories;
    }

    /**
     * Get permissions by category.
     */
    public function getPermissionsByCategory(string $category): array
    {
        $permissions = $this->permissions ?? [];
        return array_filter($permissions, fn($p) => str_starts_with($p, $category . '.'));
    }

    /**
     * Check if role is editable (non-system roles or specific system roles).
     */
    public function isEditable(): bool
    {
        if (!$this->is_system_role) {
            return true;
        }
        
        // Some system roles can be edited
        return !in_array($this->slug, [self::SUPER_ADMIN_ROLE]);
    }

    /**
     * Check if role is deletable.
     */
    public function isDeletable(): bool
    {
        // System roles cannot be deleted
        if ($this->is_system_role) {
            return false;
        }
        
        // Roles with active users cannot be deleted
        return $this->activeUsers()->count() === 0;
    }

    /**
     * Get default system roles.
     */
    public static function getDefaultSystemRoles(): array
    {
        return [
            [
                'name' => 'Super Administrator',
                'slug' => self::SUPER_ADMIN_ROLE,
                'description' => 'Full system access with all permissions',
                'permissions' => self::getAllPermissions(),
                'is_system_role' => true,
                'is_active' => true,
            ],
            [
                'name' => 'User Manager',
                'slug' => self::USER_MANAGER_ROLE,
                'description' => 'Manage users, roles, and permissions',
                'permissions' => [
                    'users.view',
                    'users.create',
                    'users.update',
                    'users.suspend',
                    'users.reactivate',
                    'users.impersonate',
                    'users.reset_password',
                    'roles.view',
                    'roles.assign',
                    'audit_logs.view',
                ],
                'is_system_role' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Tenant Manager',
                'slug' => self::TENANT_MANAGER_ROLE,
                'description' => 'Manage tenants and their configurations',
                'permissions' => [
                    'tenants.view',
                    'tenants.create',
                    'tenants.update',
                    'tenants.suspend',
                    'tenants.configure',
                    'users.view',
                    'audit_logs.view',
                ],
                'is_system_role' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Support',
                'slug' => self::SUPPORT_ROLE,
                'description' => 'Support operations and user assistance',
                'permissions' => [
                    'users.view',
                    'users.impersonate',
                    'users.reset_password',
                    'tenants.view',
                    'audit_logs.view',
                    'support.access',
                ],
                'is_system_role' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Analyst',
                'slug' => self::ANALYST_ROLE,
                'description' => 'View analytics and generate reports',
                'permissions' => [
                    'users.view',
                    'tenants.view',
                    'analytics.view',
                    'reports.generate',
                    'audit_logs.view',
                ],
                'is_system_role' => true,
                'is_active' => true,
            ],
        ];
    }

    /**
     * Get all available permissions.
     */
    public static function getAllPermissions(): array
    {
        return [
            // User management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.suspend',
            'users.reactivate',
            'users.impersonate',
            'users.reset_password',
            'users.export',
            'users.import',
            
            // Role management
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.assign',
            'roles.revoke',
            
            // Tenant management
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.delete',
            'tenants.suspend',
            'tenants.reactivate',
            'tenants.configure',
            'tenants.impersonate',
            
            // Analytics and reporting
            'analytics.view',
            'analytics.export',
            'reports.generate',
            'reports.schedule',
            
            // Audit logs
            'audit_logs.view',
            'audit_logs.export',
            'audit_logs.delete',
            
            // System administration
            'system.configure',
            'system.maintenance',
            'system.backup',
            'system.restore',
            
            // Support
            'support.access',
            'support.escalate',
            
            // Bulk operations
            'bulk.users',
            'bulk.tenants',
            
            // API management
            'api.configure',
            'api.monitor',
            
            // Security
            'security.configure',
            'security.monitor',
            'security.incident_response',
        ];
    }

    /**
     * Get permission categories.
     */
    public static function getPermissionCategories(): array
    {
        return [
            'users' => 'User Management',
            'roles' => 'Role Management',
            'tenants' => 'Tenant Management',
            'analytics' => 'Analytics & Reporting',
            'audit_logs' => 'Audit Logs',
            'system' => 'System Administration',
            'support' => 'Support Operations',
            'bulk' => 'Bulk Operations',
            'api' => 'API Management',
            'security' => 'Security Management',
        ];
    }
}