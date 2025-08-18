<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'company',
        'job_title',
        'bio',
        'country',
        'timezone',
        'avatar',
        'gdpr_consent',
        'gdpr_consent_at',
        'gdpr_consent_ip',
        'marketing_consent',
        'marketing_consent_at',
        'registration_ip',
        'registration_user_agent',
        'is_active',
        'onboarding_completed',
        'preferences',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
        'registration_ip',
        'registration_user_agent',
        'gdpr_consent_ip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
            'gdpr_consent_at' => 'datetime',
            'marketing_consent_at' => 'datetime',
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'gdpr_consent' => 'boolean',
            'marketing_consent' => 'boolean',
            'onboarding_completed' => 'boolean',
            'preferences' => 'array',
        ];
    }

    /**
     * Get the user's security logs.
     */
    public function securityLogs(): HasMany
    {
        return $this->hasMany(SecurityLog::class);
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }
        
        return $this->name ?? '';
    }

    /**
     * Check if the user has given GDPR consent.
     */
    public function hasGdprConsent(): bool
    {
        return $this->gdpr_consent && $this->gdpr_consent_at;
    }

    /**
     * Check if the user requires GDPR consent (EU users).
     */
    public function requiresGdprConsent(): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];
        
        return in_array($this->country, $euCountries);
    }

    /**
     * Mark the user's GDPR consent.
     */
    public function giveGdprConsent(string $ipAddress): void
    {
        $this->update([
            'gdpr_consent' => true,
            'gdpr_consent_at' => now(),
            'gdpr_consent_ip' => $ipAddress,
        ]);
    }

    /**
     * Check if the user is locked due to failed login attempts.
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Lock the user account for a specified duration.
     */
    public function lockAccount(int $minutes = 30): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Increment failed login attempts.
     */
    public function incrementFailedLoginAttempts(): void
    {
        $attempts = $this->failed_login_attempts + 1;
        
        $this->update(['failed_login_attempts' => $attempts]);
        
        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $this->lockAccount();
        }
    }

    /**
     * Check if onboarding is completed.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed;
    }

    /**
     * Mark onboarding as completed.
     */
    public function completeOnboarding(): void
    {
        $this->update(['onboarding_completed' => true]);
    }

    /**
     * Get the user's primary tenant.
     */
    public function tenant(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    /**
     * Get the tenants that belong to the user.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Check if user belongs to a tenant.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * Get user's role in a specific tenant.
     */
    public function roleInTenant(Tenant $tenant): ?string
    {
        $pivot = $this->tenants()->where('tenant_id', $tenant->id)->first()?->pivot;
        
        return $pivot?->role;
    }

    /**
     * Check if user has a specific role in a tenant.
     */
    public function hasRoleInTenant(Tenant $tenant, string $role): bool
    {
        return $this->roleInTenant($tenant) === $role;
    }

    /**
     * Check if user is owner of a tenant.
     */
    public function isOwnerOf(Tenant $tenant): bool
    {
        return $this->hasRoleInTenant($tenant, 'owner');
    }

    /**
     * Get user's active tenants.
     */
    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()->where('tenants.is_active', true);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    // =====================================
    // ADMIN ROLE METHODS
    // =====================================

    /**
     * Get the user's admin roles.
     */
    public function adminRoles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'user_admin_roles')
            ->withPivot(['assigned_by', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Get the user's active admin roles.
     */
    public function activeAdminRoles(): BelongsToMany
    {
        return $this->adminRoles()
            ->where('admin_roles.is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_admin_roles.expires_at')
                    ->orWhere('user_admin_roles.expires_at', '>', now());
            });
    }

    /**
     * Get the user's admin audit logs (actions performed by this admin).
     */
    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_user_id');
    }

    /**
     * Get audit logs where this user was the target.
     */
    public function targetedAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'target_id')
            ->where('target_type', self::class);
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_super_admin || $this->activeAdminRoles()->exists();
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Check if user has a specific admin role.
     */
    public function hasAdminRole(string $roleSlug): bool
    {
        return $this->activeAdminRoles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if user has any of the specified admin roles.
     */
    public function hasAnyAdminRole(array $roleSlugs): bool
    {
        return $this->activeAdminRoles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasAdminPermission(string $permission): bool
    {
        // Super admins have all permissions
        if ($this->is_super_admin) {
            return true;
        }

        // Check if any of the user's roles have this permission
        foreach ($this->activeAdminRoles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any of the specified permissions.
     */
    public function hasAnyAdminPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasAdminPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified permissions.
     */
    public function hasAllAdminPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasAdminPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permissions for the user.
     */
    public function getAllAdminPermissions(): array
    {
        if ($this->is_super_admin) {
            return AdminRole::getAllPermissions();
        }

        $permissions = [];
        foreach ($this->activeAdminRoles as $role) {
            $permissions = array_merge($permissions, $role->permissions ?? []);
        }

        return array_unique($permissions);
    }

    /**
     * Assign admin role to user.
     */
    public function assignAdminRole(AdminRole $role, int $assignedBy, ?\DateTime $expiresAt = null): void
    {
        $this->adminRoles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
            ]
        ]);
    }

    /**
     * Remove admin role from user.
     */
    public function removeAdminRole(AdminRole $role): void
    {
        $this->adminRoles()->detach($role->id);
    }

    /**
     * Check if user is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Suspend user.
     */
    public function suspend(string $reason, int $suspendedBy): void
    {
        $this->update([
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'suspended_by' => $suspendedBy,
            'is_active' => false,
        ]);
    }

    /**
     * Reactivate suspended user.
     */
    public function reactivate(): void
    {
        $this->update([
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Check if password needs to be changed.
     */
    public function needsPasswordChange(): bool
    {
        return $this->password_expires_at && $this->password_expires_at->isPast();
    }

    /**
     * Update admin notes.
     */
    public function updateAdminNotes(string $notes): void
    {
        $this->update(['admin_notes' => $notes]);
    }

    /**
     * Get user's admin dashboard URL.
     */
    public function getAdminDashboardUrlAttribute(): ?string
    {
        if (!$this->isAdmin()) {
            return null;
        }

        return route('admin.dashboard');
    }

    /**
     * Check if user can perform admin action.
     */
    public function canPerformAdminAction(string $action): bool
    {
        if (!$this->isAdmin() || !$this->is_active || $this->isSuspended()) {
            return false;
        }

        if ($this->needsPasswordChange()) {
            return false;
        }

        return $this->hasAdminPermission($action);
    }

    /**
     * Scope for admin users.
     */
    public function scopeAdmins($query)
    {
        return $query->where(function ($q) {
            $q->where('is_super_admin', true)
              ->orWhereHas('activeAdminRoles');
        });
    }

    /**
     * Scope for super admin users.
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    /**
     * Scope for suspended users.
     */
    public function scopeSuspended($query)
    {
        return $query->whereNotNull('suspended_at');
    }

    /**
     * Scope for active admin users (not suspended).
     */
    public function scopeActiveAdmins($query)
    {
        return $query->admins()
            ->where('is_active', true)
            ->whereNull('suspended_at');
    }
}