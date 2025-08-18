<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasFactory, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'domain',
        'database',
        'config',
        'is_active',
        'trial_ends_at',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'billing_address',
        'tax_id',
        // Multi-tenant security fields
        'encryption_key',
        'data_residency',
        'compliance_flags',
        'resource_limits',
        'isolation_level',
        'security_settings',
        'audit_enabled',
        'backup_settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'billing_address' => 'array',
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
            // Multi-tenant security casts
            'compliance_flags' => 'array',
            'resource_limits' => 'array',
            'security_settings' => 'array',
            'backup_settings' => 'array',
            'audit_enabled' => 'boolean',
        ];
    }

    /**
     * Get the tenant's primary user (owner).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the users that belong to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the tenant's subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the tenant's active subscription.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Get the tenant's owners.
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Get the tenant's members.
     */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Check if tenant is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant trial has expired.
     */
    public function trialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get tenant configuration value.
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set tenant configuration value.
     */
    public function setConfig(string $key, $value): void
    {
        $config = $this->config ?? [];
        $config[$key] = $value;
        $this->config = $config;
    }

    /**
     * Check if tenant has active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription()?->isActive() ?? false;
    }

    /**
     * Check if tenant can access feature.
     */
    public function canAccessFeature(string $feature): bool
    {
        $subscription = $this->subscription();
        
        if (!$subscription) {
            return false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    /**
     * Get tenant's current plan.
     */
    public function currentPlan(): ?Plan
    {
        return $this->subscription()?->plan;
    }

    // =====================================
    // MULTI-TENANT SECURITY METHODS
    // =====================================

    /**
     * Constants for isolation levels
     */
    const ISOLATION_DATABASE = 'database';
    const ISOLATION_SCHEMA = 'schema';
    const ISOLATION_ROW = 'row';

    /**
     * Constants for compliance flags
     */
    const COMPLIANCE_GDPR = 'gdpr';
    const COMPLIANCE_HIPAA = 'hipaa';
    const COMPLIANCE_SOC2 = 'soc2';
    const COMPLIANCE_ISO27001 = 'iso27001';
    const COMPLIANCE_PCI_DSS = 'pci_dss';

    /**
     * Constants for data residency
     */
    const RESIDENCY_EU = 'eu';
    const RESIDENCY_US = 'us';
    const RESIDENCY_CANADA = 'canada';
    const RESIDENCY_AUSTRALIA = 'australia';
    const RESIDENCY_ASIA = 'asia';

    /**
     * Boot the model to handle security initialization
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // Generate unique encryption key for tenant
            if (empty($tenant->encryption_key)) {
                $tenant->encryption_key = base64_encode(random_bytes(32));
            }

            // Set default isolation level
            if (empty($tenant->isolation_level)) {
                $tenant->isolation_level = self::ISOLATION_DATABASE;
            }

            // Enable audit by default
            if (is_null($tenant->audit_enabled)) {
                $tenant->audit_enabled = true;
            }

            // Set default security settings
            if (empty($tenant->security_settings)) {
                $tenant->security_settings = [
                    'enforce_2fa' => false,
                    'session_timeout' => 3600,
                    'max_login_attempts' => 5,
                    'password_expiry_days' => 90,
                    'encryption_at_rest' => true,
                    'audit_all_actions' => true,
                ];
            }

            // Set default resource limits
            if (empty($tenant->resource_limits)) {
                $tenant->resource_limits = [
                    'max_users' => 10,
                    'max_storage_gb' => 1,
                    'max_api_calls_per_hour' => 1000,
                    'max_database_connections' => 10,
                ];
            }
        });
    }

    /**
     * Get tenant's encryption key (securely)
     */
    public function getEncryptionKey(): string
    {
        return $this->encryption_key;
    }

    /**
     * Get tenant database connection configuration
     */
    public function getDatabaseConfig(): array
    {
        switch ($this->isolation_level) {
            case self::ISOLATION_DATABASE:
                $basePath = defined('LARAVEL_START') ? database_path("tenants/tenant_{$this->id}.sqlite") 
                    : __DIR__ . "/../../database/tenants/tenant_{$this->id}.sqlite";
                return [
                    'driver' => 'sqlite',
                    'database' => $basePath,
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ];
            
            case self::ISOLATION_SCHEMA:
                return [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => env('DB_DATABASE', 'laravel'),
                    'username' => env('DB_USERNAME', 'root'),
                    'password' => env('DB_PASSWORD', ''),
                    'unix_socket' => env('DB_SOCKET', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => "tenant_{$this->id}_",
                    'prefix_indexes' => true,
                    'strict' => true,
                    'engine' => null,
                ];
                
            default:
                return config('database.connections.mysql');
        }
    }

    /**
     * Get tenant connection name
     */
    public function getConnectionName(): string
    {
        return "tenant_{$this->id}";
    }

    /**
     * Check if tenant has specific compliance requirement
     */
    public function hasCompliance(string $compliance): bool
    {
        return in_array($compliance, $this->compliance_flags ?? []);
    }

    /**
     * Add compliance requirement to tenant
     */
    public function addCompliance(string $compliance): void
    {
        $flags = $this->compliance_flags ?? [];
        if (!in_array($compliance, $flags)) {
            $flags[] = $compliance;
            $this->compliance_flags = $flags;
            $this->save();
        }
    }

    /**
     * Remove compliance requirement from tenant
     */
    public function removeCompliance(string $compliance): void
    {
        $flags = $this->compliance_flags ?? [];
        $flags = array_filter($flags, fn($flag) => $flag !== $compliance);
        $this->compliance_flags = array_values($flags);
        $this->save();
    }

    /**
     * Check if tenant exceeds resource limit
     */
    public function exceedsResourceLimit(string $resource, int $currentUsage): bool
    {
        $limits = $this->resource_limits ?? [];
        return isset($limits[$resource]) && $currentUsage >= $limits[$resource];
    }

    /**
     * Get resource limit for specific resource
     */
    public function getResourceLimit(string $resource): ?int
    {
        return $this->resource_limits[$resource] ?? null;
    }

    /**
     * Set resource limit for specific resource
     */
    public function setResourceLimit(string $resource, int $limit): void
    {
        $limits = $this->resource_limits ?? [];
        $limits[$resource] = $limit;
        $this->resource_limits = $limits;
        $this->save();
    }

    /**
     * Check if tenant is in specific data residency region
     */
    public function isInRegion(string $region): bool
    {
        return $this->data_residency === $region;
    }

    /**
     * Get security setting value
     */
    public function getSecuritySetting(string $key, $default = null)
    {
        return $this->security_settings[$key] ?? $default;
    }

    /**
     * Set security setting value
     */
    public function setSecuritySetting(string $key, $value): void
    {
        $settings = $this->security_settings ?? [];
        $settings[$key] = $value;
        $this->security_settings = $settings;
        $this->save();
    }

    /**
     * Check if encryption at rest is enabled
     */
    public function hasEncryptionAtRest(): bool
    {
        return $this->getSecuritySetting('encryption_at_rest', true);
    }

    /**
     * Check if 2FA is enforced
     */
    public function requires2FA(): bool
    {
        return $this->getSecuritySetting('enforce_2fa', false);
    }

    /**
     * Get session timeout in seconds
     */
    public function getSessionTimeout(): int
    {
        return $this->getSecuritySetting('session_timeout', 3600);
    }

    /**
     * Check if audit logging is enabled
     */
    public function isAuditEnabled(): bool
    {
        return $this->audit_enabled;
    }

    /**
     * Get backup settings
     */
    public function getBackupSettings(): array
    {
        return $this->backup_settings ?? [
            'enabled' => true,
            'frequency' => 'daily',
            'retention_days' => 30,
            'encrypt_backups' => true,
            'backup_location' => 'local',
        ];
    }

    /**
     * Scope for active and secure tenants
     */
    public function scopeActiveAndSecure($query)
    {
        return $query->where('is_active', true)
                    ->whereNotNull('encryption_key');
    }

    /**
     * Scope for tenants with specific compliance
     */
    public function scopeWithCompliance($query, string $compliance)
    {
        return $query->whereJsonContains('compliance_flags', $compliance);
    }

    /**
     * Scope for tenants in specific region
     */
    public function scopeInRegion($query, string $region)
    {
        return $query->where('data_residency', $region);
    }

    /**
     * Scope for tenants with audit enabled
     */
    public function scopeWithAudit($query)
    {
        return $query->where('audit_enabled', true);
    }
}