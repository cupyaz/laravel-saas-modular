<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleInstallation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'module_id',
        'tenant_id',
        'version',
        'status',
        'config',
        'installed_at',
        'activated_at',
        'deactivated_at',
        'last_updated_at',
        'auto_update',
        'installation_method',
        'installation_source',
        'license_key',
        'error_log',
        'performance_data'
    ];

    protected $casts = [
        'config' => 'array',
        'error_log' => 'array',
        'performance_data' => 'array',
        'installed_at' => 'datetime',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'auto_update' => 'boolean'
    ];

    // Installation Status Constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_INSTALLING = 'installing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ERROR = 'error';
    public const STATUS_UPDATING = 'updating';
    public const STATUS_UNINSTALLING = 'uninstalling';

    // Installation Methods
    public const METHOD_MANUAL = 'manual';
    public const METHOD_API = 'api';
    public const METHOD_MARKETPLACE = 'marketplace';
    public const METHOD_BULK = 'bulk';

    /**
     * Get the module that this installation belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the tenant that this installation belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to get only active installations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get only inactive installations.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope to get installations with errors.
     */
    public function scopeWithErrors($query)
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if installation is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if installation has errors.
     */
    public function hasErrors(): bool
    {
        return $this->status === self::STATUS_ERROR || !empty($this->error_log);
    }

    /**
     * Check if module can be updated.
     */
    public function canUpdate(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $latestVersion = $this->module->getLatestVersion();
        return $latestVersion && version_compare($this->version, $latestVersion->version, '<');
    }

    /**
     * Get the next available version for update.
     */
    public function getNextVersion(): ?ModuleVersion
    {
        return $this->module->versions()
                   ->where('version', '>', $this->version)
                   ->where('is_stable', true)
                   ->orderBy('version', 'asc')
                   ->first();
    }

    /**
     * Get installation configuration merged with defaults.
     */
    public function getEffectiveConfig(): array
    {
        $defaultConfig = $this->module->getDefaultConfig();
        $instanceConfig = $this->config ?? [];

        return array_merge($defaultConfig, $instanceConfig);
    }

    /**
     * Validate current configuration against module schema.
     */
    public function validateConfiguration(): array
    {
        $config = $this->config ?? [];
        return $this->module->validateConfig($config);
    }

    /**
     * Update module configuration.
     */
    public function updateConfig(array $newConfig): bool
    {
        $errors = $this->module->validateConfig($newConfig);
        
        if (!empty($errors)) {
            return false;
        }

        $this->update(['config' => $newConfig]);
        return true;
    }

    /**
     * Activate the module installation.
     */
    public function activate(): bool
    {
        if ($this->status === self::STATUS_ERROR) {
            return false;
        }

        // Check if module is compatible
        if (!$this->module->isCompatible()) {
            $this->logError('Module is not compatible with current system', [
                'compatibility_issues' => $this->module->getCompatibilityIssues()
            ]);
            return false;
        }

        // Validate configuration
        $configErrors = $this->validateConfiguration();
        if (!empty($configErrors)) {
            $this->logError('Configuration validation failed', [
                'errors' => $configErrors
            ]);
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
            'deactivated_at' => null
        ]);

        return true;
    }

    /**
     * Deactivate the module installation.
     */
    public function deactivate(): bool
    {
        $this->update([
            'status' => self::STATUS_INACTIVE,
            'deactivated_at' => now()
        ]);

        return true;
    }

    /**
     * Log an error for this installation.
     */
    public function logError(string $message, array $context = []): void
    {
        $errorLog = $this->error_log ?? [];
        
        $errorLog[] = [
            'timestamp' => now()->toISOString(),
            'message' => $message,
            'context' => $context,
            'version' => $this->version
        ];

        // Keep only last 50 error entries
        if (count($errorLog) > 50) {
            $errorLog = array_slice($errorLog, -50);
        }

        $this->update([
            'error_log' => $errorLog,
            'status' => self::STATUS_ERROR
        ]);
    }

    /**
     * Clear error log and reset status.
     */
    public function clearErrors(): void
    {
        $this->update([
            'error_log' => [],
            'status' => self::STATUS_INACTIVE
        ]);
    }

    /**
     * Record performance metrics.
     */
    public function recordPerformance(array $metrics): void
    {
        $performanceData = $this->performance_data ?? [];
        
        $performanceData[] = [
            'timestamp' => now()->toISOString(),
            'metrics' => $metrics
        ];

        // Keep only last 100 performance entries
        if (count($performanceData) > 100) {
            $performanceData = array_slice($performanceData, -100);
        }

        $this->update(['performance_data' => $performanceData]);
    }

    /**
     * Get installation health score (0-100).
     */
    public function getHealthScore(): int
    {
        $score = 100;

        // Deduct for errors
        if ($this->hasErrors()) {
            $score -= 30;
        }

        // Deduct for outdated version
        if ($this->canUpdate()) {
            $score -= 10;
        }

        // Deduct for inactive status
        if (!$this->isActive()) {
            $score -= 20;
        }

        // Check performance metrics
        if (!empty($this->performance_data)) {
            $recentMetrics = collect($this->performance_data)->take(-10);
            $avgResponseTime = $recentMetrics->avg('metrics.response_time');
            
            if ($avgResponseTime > 1000) { // 1 second
                $score -= 15;
            } elseif ($avgResponseTime > 500) { // 0.5 seconds
                $score -= 5;
            }
        }

        return max(0, $score);
    }

    /**
     * Get status badge color for UI.
     */
    public function getStatusBadgeColor(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_ERROR => 'red',
            self::STATUS_PENDING, self::STATUS_INSTALLING, self::STATUS_UPDATING => 'yellow',
            self::STATUS_UNINSTALLING => 'orange',
            default => 'gray'
        };
    }

    /**
     * Get human-readable status text.
     */
    public function getStatusText(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending Installation',
            self::STATUS_INSTALLING => 'Installing...',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ERROR => 'Error',
            self::STATUS_UPDATING => 'Updating...',
            self::STATUS_UNINSTALLING => 'Uninstalling...',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get installation age in days.
     */
    public function getInstallationAge(): int
    {
        return $this->installed_at ? $this->installed_at->diffInDays(now()) : 0;
    }

    /**
     * Check if installation needs attention (errors, outdated, etc.).
     */
    public function needsAttention(): bool
    {
        return $this->hasErrors() || 
               $this->canUpdate() || 
               !$this->isActive() || 
               $this->getHealthScore() < 70;
    }
}