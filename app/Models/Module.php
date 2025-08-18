<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'author',
        'category',
        'license',
        'repository_url',
        'documentation_url',
        'icon',
        'screenshots',
        'requirements',
        'permissions',
        'config_schema',
        'default_config',
        'installation_script',
        'is_active',
        'is_core',
        'is_featured',
        'download_count',
        'rating',
        'price',
        'published_at',
        'metadata'
    ];

    protected $casts = [
        'screenshots' => 'array',
        'requirements' => 'array',
        'permissions' => 'array',
        'config_schema' => 'array',
        'default_config' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_core' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime'
    ];

    // Module Status Constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DEPRECATED = 'deprecated';

    // Module Categories
    public const CATEGORIES = [
        'authentication' => 'Authentication & Security',
        'payment' => 'Payment & Billing',
        'analytics' => 'Analytics & Reporting',
        'integration' => 'Third-party Integrations',
        'ui' => 'User Interface',
        'workflow' => 'Workflow & Automation',
        'communication' => 'Communication',
        'content' => 'Content Management',
        'ecommerce' => 'E-commerce',
        'utility' => 'Utilities',
        'custom' => 'Custom Solutions'
    ];

    /**
     * Get module installations for this module.
     */
    public function installations(): HasMany
    {
        return $this->hasMany(ModuleInstallation::class);
    }

    /**
     * Get module versions for this module.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ModuleVersion::class);
    }

    /**
     * Get module reviews.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ModuleReview::class);
    }

    /**
     * Scope to get only active modules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only featured modules.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to search modules by name or description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('author', 'like', "%{$search}%");
        });
    }

    /**
     * Check if module is compatible with current system.
     */
    public function isCompatible(): bool
    {
        $requirements = $this->requirements ?? [];
        
        // Check PHP version
        if (isset($requirements['php']) && !version_compare(PHP_VERSION, $requirements['php'], '>=')) {
            return false;
        }

        // Check Laravel version
        if (isset($requirements['laravel'])) {
            $laravelVersion = app()->version();
            if (!version_compare($laravelVersion, $requirements['laravel'], '>=')) {
                return false;
            }
        }

        // Check required PHP extensions
        if (isset($requirements['extensions'])) {
            foreach ($requirements['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    return false;
                }
            }
        }

        // Check required modules/dependencies
        if (isset($requirements['modules'])) {
            foreach ($requirements['modules'] as $module => $version) {
                $installedModule = self::where('slug', $module)->first();
                if (!$installedModule || !version_compare($installedModule->version, $version, '>=')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get module compatibility issues.
     */
    public function getCompatibilityIssues(): array
    {
        $issues = [];
        $requirements = $this->requirements ?? [];

        // Check PHP version
        if (isset($requirements['php']) && !version_compare(PHP_VERSION, $requirements['php'], '>=')) {
            $issues[] = [
                'type' => 'php_version',
                'message' => "PHP {$requirements['php']} or higher required (current: " . PHP_VERSION . ")",
                'severity' => 'error'
            ];
        }

        // Check Laravel version
        if (isset($requirements['laravel'])) {
            $laravelVersion = app()->version();
            if (!version_compare($laravelVersion, $requirements['laravel'], '>=')) {
                $issues[] = [
                    'type' => 'laravel_version',
                    'message' => "Laravel {$requirements['laravel']} or higher required (current: {$laravelVersion})",
                    'severity' => 'error'
                ];
            }
        }

        // Check required PHP extensions
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

        // Check required modules/dependencies
        if (isset($requirements['modules'])) {
            foreach ($requirements['modules'] as $module => $version) {
                $installedModule = self::where('slug', $module)->first();
                if (!$installedModule) {
                    $issues[] = [
                        'type' => 'missing_dependency',
                        'message' => "Module '{$module}' is required but not installed",
                        'severity' => 'error'
                    ];
                } elseif (!version_compare($installedModule->version, $version, '>=')) {
                    $issues[] = [
                        'type' => 'dependency_version',
                        'message' => "Module '{$module}' version {$version} or higher required (installed: {$installedModule->version})",
                        'severity' => 'warning'
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Get installation status for a specific tenant.
     */
    public function getInstallationStatus(?int $tenantId = null): ?ModuleInstallation
    {
        return $this->installations()
                   ->where('tenant_id', $tenantId)
                   ->first();
    }

    /**
     * Check if module is installed for a tenant.
     */
    public function isInstalledForTenant(?int $tenantId = null): bool
    {
        return $this->installations()
                   ->where('tenant_id', $tenantId)
                   ->where('status', 'active')
                   ->exists();
    }

    /**
     * Get module configuration schema.
     */
    public function getConfigSchema(): array
    {
        return $this->config_schema ?? [];
    }

    /**
     * Get default configuration.
     */
    public function getDefaultConfig(): array
    {
        return $this->default_config ?? [];
    }

    /**
     * Validate configuration against schema.
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        $schema = $this->getConfigSchema();

        foreach ($schema as $field => $rules) {
            $value = $config[$field] ?? null;
            
            // Check required fields
            if (($rules['required'] ?? false) && is_null($value)) {
                $errors[$field][] = 'This field is required';
                continue;
            }

            if (!is_null($value)) {
                // Type validation
                if (isset($rules['type']) && !$this->validateConfigType($value, $rules['type'])) {
                    $errors[$field][] = "Invalid type, expected {$rules['type']}";
                }

                // Min/Max validation for numbers
                if ($rules['type'] === 'number') {
                    if (isset($rules['min']) && $value < $rules['min']) {
                        $errors[$field][] = "Value must be at least {$rules['min']}";
                    }
                    if (isset($rules['max']) && $value > $rules['max']) {
                        $errors[$field][] = "Value must not exceed {$rules['max']}";
                    }
                }

                // Length validation for strings
                if ($rules['type'] === 'string') {
                    if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                        $errors[$field][] = "Must be at least {$rules['min_length']} characters";
                    }
                    if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                        $errors[$field][] = "Must not exceed {$rules['max_length']} characters";
                    }
                }

                // Enum validation
                if (isset($rules['options']) && !in_array($value, $rules['options'])) {
                    $options = implode(', ', $rules['options']);
                    $errors[$field][] = "Invalid option. Allowed values: {$options}";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate configuration field type.
     */
    private function validateConfigType($value, string $type): bool
    {
        return match($type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || is_array($value),
            default => true
        };
    }

    /**
     * Get module display name with version.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} v{$this->version}";
    }

    /**
     * Get module icon URL.
     */
    public function getIconUrlAttribute(): string
    {
        if ($this->icon && filter_var($this->icon, FILTER_VALIDATE_URL)) {
            return $this->icon;
        }

        return "/images/modules/default-icon.svg";
    }

    /**
     * Get module rating as stars.
     */
    public function getStarsAttribute(): string
    {
        $rating = $this->rating ?? 0;
        $stars = str_repeat('★', floor($rating));
        $halfStar = ($rating - floor($rating) >= 0.5) ? '☆' : '';
        $emptyStars = str_repeat('☆', 5 - strlen($stars) - strlen($halfStar));
        
        return $stars . $halfStar . $emptyStars;
    }

    /**
     * Increment download count.
     */
    public function incrementDownloads(): void
    {
        $this->increment('download_count');
    }

    /**
     * Calculate and update average rating.
     */
    public function updateRating(): void
    {
        $averageRating = $this->reviews()->avg('rating');
        $this->update(['rating' => round($averageRating, 1)]);
    }

    /**
     * Check if module has updates available.
     */
    public function hasUpdates(): bool
    {
        return $this->versions()
                   ->where('version', '>', $this->version)
                   ->where('is_stable', true)
                   ->exists();
    }

    /**
     * Get latest available version.
     */
    public function getLatestVersion(): ?ModuleVersion
    {
        return $this->versions()
                   ->where('is_stable', true)
                   ->orderBy('version', 'desc')
                   ->first();
    }

    /**
     * Get module size in MB.
     */
    public function getSizeAttribute(): string
    {
        $size = $this->metadata['size'] ?? 0;
        
        if ($size < 1024) {
            return $size . ' KB';
        }
        
        return round($size / 1024, 1) . ' MB';
    }
}