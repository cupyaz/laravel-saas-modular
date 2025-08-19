<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalyticsReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description', 
        'type',
        'category',
        'filters',
        'metrics',
        'dimensions',
        'date_range',
        'schedule',
        'format',
        'recipients',
        'is_active',
        'is_public',
        'created_by',
        'tenant_id',
        'last_generated_at',
        'generation_count',
        'file_path',
        'file_size',
        'metadata'
    ];

    protected $casts = [
        'filters' => 'array',
        'metrics' => 'array', 
        'dimensions' => 'array',
        'date_range' => 'array',
        'schedule' => 'array',
        'recipients' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'last_generated_at' => 'datetime'
    ];

    // Report Types
    public const TYPE_USER_ANALYTICS = 'user_analytics';
    public const TYPE_MODULE_ANALYTICS = 'module_analytics';
    public const TYPE_REVENUE_ANALYTICS = 'revenue_analytics';
    public const TYPE_USAGE_ANALYTICS = 'usage_analytics';
    public const TYPE_ENGAGEMENT_ANALYTICS = 'engagement_analytics';
    public const TYPE_PERFORMANCE_ANALYTICS = 'performance_analytics';
    public const TYPE_CUSTOM = 'custom';

    // Report Categories
    public const CATEGORY_DASHBOARD = 'dashboard';
    public const CATEGORY_OPERATIONAL = 'operational';
    public const CATEGORY_BUSINESS = 'business';
    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_COMPLIANCE = 'compliance';

    // Export Formats
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_CSV = 'csv';
    public const FORMAT_EXCEL = 'excel';
    public const FORMAT_JSON = 'json';
    public const FORMAT_HTML = 'html';

    // Schedule Types
    public const SCHEDULE_ONCE = 'once';
    public const SCHEDULE_DAILY = 'daily';
    public const SCHEDULE_WEEKLY = 'weekly';
    public const SCHEDULE_MONTHLY = 'monthly';
    public const SCHEDULE_QUARTERLY = 'quarterly';

    /**
     * Get the user who created this report.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the tenant this report belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get report executions.
     */
    public function executions()
    {
        return $this->hasMany(AnalyticsReportExecution::class, 'report_id');
    }

    /**
     * Scope for active reports.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for public reports.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope by report type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for tenant-specific reports.
     */
    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for scheduled reports.
     */
    public function scopeScheduled($query)
    {
        return $query->whereNotNull('schedule')
                     ->where('schedule->type', '!=', self::SCHEDULE_ONCE);
    }

    /**
     * Check if report has schedule.
     */
    public function isScheduled(): bool
    {
        return !empty($this->schedule) && 
               ($this->schedule['type'] ?? null) !== self::SCHEDULE_ONCE;
    }

    /**
     * Check if report is due for generation.
     */
    public function isDueForGeneration(): bool
    {
        if (!$this->isScheduled() || !$this->is_active) {
            return false;
        }

        $lastGenerated = $this->last_generated_at;
        if (!$lastGenerated) {
            return true;
        }

        $scheduleType = $this->schedule['type'] ?? null;
        $now = now();

        return match($scheduleType) {
            self::SCHEDULE_DAILY => $lastGenerated->diffInDays($now) >= 1,
            self::SCHEDULE_WEEKLY => $lastGenerated->diffInWeeks($now) >= 1,
            self::SCHEDULE_MONTHLY => $lastGenerated->diffInMonths($now) >= 1,
            self::SCHEDULE_QUARTERLY => $lastGenerated->diffInMonths($now) >= 3,
            default => false
        };
    }

    /**
     * Get available report types with descriptions.
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_USER_ANALYTICS => 'User Analytics & Engagement',
            self::TYPE_MODULE_ANALYTICS => 'Module Usage & Performance',
            self::TYPE_REVENUE_ANALYTICS => 'Revenue & Subscription Analytics',
            self::TYPE_USAGE_ANALYTICS => 'Platform Usage Statistics',
            self::TYPE_ENGAGEMENT_ANALYTICS => 'User Engagement Metrics',
            self::TYPE_PERFORMANCE_ANALYTICS => 'System Performance Metrics',
            self::TYPE_CUSTOM => 'Custom Report'
        ];
    }

    /**
     * Get available categories with descriptions.
     */
    public static function getAvailableCategories(): array
    {
        return [
            self::CATEGORY_DASHBOARD => 'Dashboard & KPI Reports',
            self::CATEGORY_OPERATIONAL => 'Operational Reports',
            self::CATEGORY_BUSINESS => 'Business Intelligence',
            self::CATEGORY_TECHNICAL => 'Technical Performance',
            self::CATEGORY_COMPLIANCE => 'Compliance & Audit'
        ];
    }

    /**
     * Get available export formats.
     */
    public static function getAvailableFormats(): array
    {
        return [
            self::FORMAT_PDF => 'PDF Document',
            self::FORMAT_CSV => 'CSV Spreadsheet',
            self::FORMAT_EXCEL => 'Excel Spreadsheet',
            self::FORMAT_JSON => 'JSON Data',
            self::FORMAT_HTML => 'HTML Report'
        ];
    }

    /**
     * Get default metrics for report type.
     */
    public static function getDefaultMetrics(string $type): array
    {
        return match($type) {
            self::TYPE_USER_ANALYTICS => [
                'total_users',
                'active_users',
                'new_registrations',
                'user_retention_rate',
                'average_session_duration'
            ],
            self::TYPE_MODULE_ANALYTICS => [
                'total_modules',
                'module_installations',
                'module_ratings',
                'popular_modules',
                'module_revenue'
            ],
            self::TYPE_REVENUE_ANALYTICS => [
                'total_revenue',
                'mrr',
                'arr',
                'subscription_growth',
                'churn_rate'
            ],
            self::TYPE_USAGE_ANALYTICS => [
                'api_calls',
                'feature_usage',
                'storage_usage',
                'bandwidth_usage'
            ],
            self::TYPE_ENGAGEMENT_ANALYTICS => [
                'page_views',
                'feature_adoption',
                'user_actions',
                'session_frequency'
            ],
            self::TYPE_PERFORMANCE_ANALYTICS => [
                'response_times',
                'error_rates',
                'uptime',
                'resource_utilization'
            ],
            default => []
        };
    }

    /**
     * Get default dimensions for report type.
     */
    public static function getDefaultDimensions(string $type): array
    {
        return match($type) {
            self::TYPE_USER_ANALYTICS => [
                'date',
                'user_type',
                'registration_source',
                'user_segment'
            ],
            self::TYPE_MODULE_ANALYTICS => [
                'date',
                'module_category',
                'module_type',
                'tenant'
            ],
            self::TYPE_REVENUE_ANALYTICS => [
                'date',
                'plan_type',
                'payment_method',
                'subscription_status'
            ],
            default => ['date']
        ];
    }

    /**
     * Generate file name for report.
     */
    public function generateFileName(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $safeName = str_replace([' ', '/', '\\'], '_', $this->name);
        
        return "{$safeName}_{$timestamp}.{$this->format}";
    }

    /**
     * Get human-readable schedule description.
     */
    public function getScheduleDescription(): string
    {
        if (!$this->isScheduled()) {
            return 'One-time generation';
        }

        $scheduleType = $this->schedule['type'] ?? 'unknown';
        $scheduleTime = $this->schedule['time'] ?? '00:00';

        return match($scheduleType) {
            self::SCHEDULE_DAILY => "Daily at {$scheduleTime}",
            self::SCHEDULE_WEEKLY => "Weekly on " . ($this->schedule['day'] ?? 'Monday') . " at {$scheduleTime}",
            self::SCHEDULE_MONTHLY => "Monthly on day " . ($this->schedule['day'] ?? '1') . " at {$scheduleTime}",
            self::SCHEDULE_QUARTERLY => "Quarterly on the 1st at {$scheduleTime}",
            default => 'Custom schedule'
        };
    }

    /**
     * Update generation statistics.
     */
    public function updateGenerationStats(?string $filePath = null, ?int $fileSize = null): void
    {
        $this->update([
            'last_generated_at' => now(),
            'generation_count' => $this->generation_count + 1,
            'file_path' => $filePath,
            'file_size' => $fileSize
        ]);
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
     * Check if user can access this report.
     */
    public function canAccess(User $user): bool
    {
        // Public reports can be accessed by authenticated users
        if ($this->is_public) {
            return true;
        }

        // Creator can always access
        if ($this->created_by === $user->id) {
            return true;
        }

        // Tenant admins can access tenant reports
        if ($this->tenant_id && $user->tenant_id === $this->tenant_id && $user->isAdmin()) {
            return true;
        }

        // Super admins can access all reports
        if ($user->isSuperAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Validate report configuration.
     */
    public static function validateConfiguration(array $config): array
    {
        $errors = [];

        // Validate required fields
        if (empty($config['name'])) {
            $errors['name'] = 'Report name is required';
        }

        if (empty($config['type'])) {
            $errors['type'] = 'Report type is required';
        } elseif (!array_key_exists($config['type'], self::getAvailableTypes())) {
            $errors['type'] = 'Invalid report type';
        }

        // Validate metrics
        if (empty($config['metrics']) || !is_array($config['metrics'])) {
            $errors['metrics'] = 'At least one metric is required';
        }

        // Validate date range
        if (!empty($config['date_range'])) {
            if (!isset($config['date_range']['start']) || !isset($config['date_range']['end'])) {
                $errors['date_range'] = 'Both start and end dates are required';
            }
        }

        // Validate schedule
        if (!empty($config['schedule']) && !empty($config['schedule']['type'])) {
            $validSchedules = [
                self::SCHEDULE_ONCE,
                self::SCHEDULE_DAILY,
                self::SCHEDULE_WEEKLY,
                self::SCHEDULE_MONTHLY,
                self::SCHEDULE_QUARTERLY
            ];
            
            if (!in_array($config['schedule']['type'], $validSchedules)) {
                $errors['schedule'] = 'Invalid schedule type';
            }
        }

        return $errors;
    }
}