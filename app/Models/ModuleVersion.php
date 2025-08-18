<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'module_id',
        'version',
        'title',
        'description',
        'changelog',
        'release_notes',
        'compatibility',
        'requirements',
        'download_url',
        'file_size',
        'file_hash',
        'is_stable',
        'is_beta',
        'is_alpha',
        'is_pre_release',
        'min_core_version',
        'max_core_version',
        'upgrade_scripts',
        'rollback_scripts',
        'breaking_changes',
        'security_fixes',
        'performance_improvements',
        'published_at',
        'deprecated_at',
        'end_of_support_at',
        'download_count',
        'installation_success_rate',
        'metadata'
    ];

    protected $casts = [
        'changelog' => 'array',
        'compatibility' => 'array',
        'requirements' => 'array',
        'upgrade_scripts' => 'array',
        'rollback_scripts' => 'array',
        'breaking_changes' => 'array',
        'security_fixes' => 'array',
        'performance_improvements' => 'array',
        'metadata' => 'array',
        'is_stable' => 'boolean',
        'is_beta' => 'boolean',
        'is_alpha' => 'boolean',
        'is_pre_release' => 'boolean',
        'published_at' => 'datetime',
        'deprecated_at' => 'datetime',
        'end_of_support_at' => 'datetime',
        'installation_success_rate' => 'decimal:2'
    ];

    // Release Types
    public const TYPE_STABLE = 'stable';
    public const TYPE_BETA = 'beta';
    public const TYPE_ALPHA = 'alpha';
    public const TYPE_PRE_RELEASE = 'pre-release';

    // Severity Levels for Changes
    public const SEVERITY_PATCH = 'patch';
    public const SEVERITY_MINOR = 'minor';
    public const SEVERITY_MAJOR = 'major';

    /**
     * Get the module that this version belongs to.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get installations using this version.
     */
    public function installations(): HasMany
    {
        return $this->hasMany(ModuleInstallation::class, 'version', 'version');
    }

    /**
     * Scope to get only stable versions.
     */
    public function scopeStable($query)
    {
        return $query->where('is_stable', true);
    }

    /**
     * Scope to get only beta versions.
     */
    public function scopeBeta($query)
    {
        return $query->where('is_beta', true);
    }

    /**
     * Scope to get only alpha versions.
     */
    public function scopeAlpha($query)
    {
        return $query->where('is_alpha', true);
    }

    /**
     * Scope to get pre-release versions.
     */
    public function scopePreRelease($query)
    {
        return $query->where('is_pre_release', true);
    }

    /**
     * Scope to get published versions.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    /**
     * Scope to get supported versions.
     */
    public function scopeSupported($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_of_support_at')
              ->orWhere('end_of_support_at', '>', now());
        });
    }

    /**
     * Scope to order by version number.
     */
    public function scopeVersionOrder($query, string $direction = 'asc')
    {
        return $query->orderBy('version', $direction);
    }

    /**
     * Check if version is compatible with system.
     */
    public function isCompatible(): bool
    {
        $systemVersion = app()->version();

        // Check minimum core version
        if ($this->min_core_version && !version_compare($systemVersion, $this->min_core_version, '>=')) {
            return false;
        }

        // Check maximum core version
        if ($this->max_core_version && !version_compare($systemVersion, $this->max_core_version, '<=')) {
            return false;
        }

        // Check requirements
        $requirements = $this->requirements ?? [];
        
        // Check PHP version
        if (isset($requirements['php']) && !version_compare(PHP_VERSION, $requirements['php'], '>=')) {
            return false;
        }

        // Check extensions
        if (isset($requirements['extensions'])) {
            foreach ($requirements['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get compatibility issues.
     */
    public function getCompatibilityIssues(): array
    {
        $issues = [];
        $systemVersion = app()->version();

        // Check core version compatibility
        if ($this->min_core_version && !version_compare($systemVersion, $this->min_core_version, '>=')) {
            $issues[] = [
                'type' => 'core_version_too_old',
                'message' => "Requires minimum core version {$this->min_core_version} (current: {$systemVersion})",
                'severity' => 'error'
            ];
        }

        if ($this->max_core_version && !version_compare($systemVersion, $this->max_core_version, '<=')) {
            $issues[] = [
                'type' => 'core_version_too_new',
                'message' => "Maximum supported core version {$this->max_core_version} (current: {$systemVersion})",
                'severity' => 'warning'
            ];
        }

        // Check requirements
        $requirements = $this->requirements ?? [];
        
        if (isset($requirements['php']) && !version_compare(PHP_VERSION, $requirements['php'], '>=')) {
            $issues[] = [
                'type' => 'php_version',
                'message' => "PHP {$requirements['php']} or higher required (current: " . PHP_VERSION . ")",
                'severity' => 'error'
            ];
        }

        if (isset($requirements['extensions'])) {
            foreach ($requirements['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    $issues[] = [
                        'type' => 'missing_extension',
                        'message' => "PHP extension '{$extension}' is required but not installed",
                        'severity' => 'error'
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Get release type for this version.
     */
    public function getReleaseType(): string
    {
        if ($this->is_alpha) return self::TYPE_ALPHA;
        if ($this->is_beta) return self::TYPE_BETA;
        if ($this->is_pre_release) return self::TYPE_PRE_RELEASE;
        return self::TYPE_STABLE;
    }

    /**
     * Get change severity based on version number.
     */
    public function getChangeSeverity(?string $previousVersion = null): string
    {
        if (!$previousVersion) {
            $previousVersion = $this->module->versions()
                                          ->where('version', '<', $this->version)
                                          ->orderBy('version', 'desc')
                                          ->first()?->version;
        }

        if (!$previousVersion) {
            return self::SEVERITY_MAJOR;
        }

        $current = explode('.', $this->version);
        $previous = explode('.', $previousVersion);

        // Major version change
        if (($current[0] ?? 0) > ($previous[0] ?? 0)) {
            return self::SEVERITY_MAJOR;
        }

        // Minor version change
        if (($current[1] ?? 0) > ($previous[1] ?? 0)) {
            return self::SEVERITY_MINOR;
        }

        // Patch version change
        return self::SEVERITY_PATCH;
    }

    /**
     * Check if version is deprecated.
     */
    public function isDeprecated(): bool
    {
        return $this->deprecated_at && $this->deprecated_at <= now();
    }

    /**
     * Check if version is supported.
     */
    public function isSupported(): bool
    {
        return !$this->end_of_support_at || $this->end_of_support_at > now();
    }

    /**
     * Check if version is published.
     */
    public function isPublished(): bool
    {
        return $this->published_at && $this->published_at <= now();
    }

    /**
     * Check if version has security fixes.
     */
    public function hasSecurityFixes(): bool
    {
        return !empty($this->security_fixes);
    }

    /**
     * Check if version has breaking changes.
     */
    public function hasBreakingChanges(): bool
    {
        return !empty($this->breaking_changes);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get version badge color for UI.
     */
    public function getVersionBadgeColor(): string
    {
        return match($this->getReleaseType()) {
            self::TYPE_STABLE => 'green',
            self::TYPE_BETA => 'yellow',
            self::TYPE_ALPHA => 'orange',
            self::TYPE_PRE_RELEASE => 'red',
            default => 'gray'
        };
    }

    /**
     * Get version display name with type.
     */
    public function getDisplayName(): string
    {
        $name = $this->version;
        $type = $this->getReleaseType();
        
        if ($type !== self::TYPE_STABLE) {
            $name .= ' (' . ucfirst($type) . ')';
        }

        return $name;
    }

    /**
     * Get installation success rate percentage.
     */
    public function getSuccessRatePercentage(): string
    {
        return number_format($this->installation_success_rate * 100, 1) . '%';
    }

    /**
     * Increment download count.
     */
    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    /**
     * Update installation success rate.
     */
    public function updateSuccessRate(): void
    {
        $totalInstallations = $this->installations()->count();
        $successfulInstallations = $this->installations()
                                       ->whereIn('status', [
                                           ModuleInstallation::STATUS_ACTIVE,
                                           ModuleInstallation::STATUS_INACTIVE
                                       ])
                                       ->count();

        if ($totalInstallations > 0) {
            $rate = $successfulInstallations / $totalInstallations;
            $this->update(['installation_success_rate' => $rate]);
        }
    }

    /**
     * Get upgrade path from another version.
     */
    public function getUpgradePath(string $fromVersion): array
    {
        return $this->upgrade_scripts ?? [];
    }

    /**
     * Get rollback path to another version.
     */
    public function getRollbackPath(string $toVersion): array
    {
        return $this->rollback_scripts ?? [];
    }

    /**
     * Check if direct upgrade is possible from version.
     */
    public function canUpgradeFrom(string $fromVersion): bool
    {
        $upgradePath = $this->getUpgradePath($fromVersion);
        return !empty($upgradePath) || $this->isMinorOrPatchUpdate($fromVersion);
    }

    /**
     * Check if this is a minor or patch update.
     */
    private function isMinorOrPatchUpdate(string $fromVersion): bool
    {
        $severity = $this->getChangeSeverity($fromVersion);
        return in_array($severity, [self::SEVERITY_MINOR, self::SEVERITY_PATCH]);
    }

    /**
     * Get changelog for specific category.
     */
    public function getChangelogByCategory(string $category): array
    {
        $changelog = $this->changelog ?? [];
        return $changelog[$category] ?? [];
    }

    /**
     * Get all changelog categories.
     */
    public function getChangelogCategories(): array
    {
        return array_keys($this->changelog ?? []);
    }
}