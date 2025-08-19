<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AnalyticsMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_name',
        'metric_type',
        'category',
        'value',
        'dimensions',
        'tenant_id',
        'recorded_at',
        'period_start',
        'period_end',
        'metadata'
    ];

    protected $casts = [
        'dimensions' => 'array',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'value' => 'decimal:4'
    ];

    // Metric Types
    public const TYPE_COUNTER = 'counter';
    public const TYPE_GAUGE = 'gauge';
    public const TYPE_HISTOGRAM = 'histogram';
    public const TYPE_RATE = 'rate';
    public const TYPE_PERCENTAGE = 'percentage';

    // Metric Categories
    public const CATEGORY_USER = 'user';
    public const CATEGORY_MODULE = 'module';
    public const CATEGORY_REVENUE = 'revenue';
    public const CATEGORY_USAGE = 'usage';
    public const CATEGORY_PERFORMANCE = 'performance';
    public const CATEGORY_ENGAGEMENT = 'engagement';
    public const CATEGORY_SYSTEM = 'system';

    /**
     * Get the tenant this metric belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope by metric name.
     */
    public function scopeByMetric($query, string $metricName)
    {
        return $query->where('metric_name', $metricName);
    }

    /**
     * Scope by metric type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by tenant.
     */
    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope by date range.
     */
    public function scopeByDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    /**
     * Scope by period.
     */
    public function scopeByPeriod($query, string $period)
    {
        $now = now();
        
        return match($period) {
            'today' => $query->whereDate('recorded_at', $now->toDateString()),
            'yesterday' => $query->whereDate('recorded_at', $now->subDay()->toDateString()),
            'week' => $query->whereBetween('recorded_at', [$now->startOfWeek(), $now->endOfWeek()]),
            'last_week' => $query->whereBetween('recorded_at', [
                $now->subWeek()->startOfWeek(),
                $now->subWeek()->endOfWeek()
            ]),
            'month' => $query->whereMonth('recorded_at', $now->month)
                             ->whereYear('recorded_at', $now->year),
            'last_month' => $query->whereMonth('recorded_at', $now->subMonth()->month)
                                  ->whereYear('recorded_at', $now->subMonth()->year),
            'quarter' => $query->whereBetween('recorded_at', [$now->startOfQuarter(), $now->endOfQuarter()]),
            'year' => $query->whereYear('recorded_at', $now->year),
            default => $query
        };
    }

    /**
     * Get aggregated value for metric.
     */
    public static function getAggregatedValue(
        string $metricName,
        string $aggregation = 'sum',
        ?int $tenantId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): float {
        $query = static::byMetric($metricName);

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return match($aggregation) {
            'sum' => $query->sum('value'),
            'avg' => $query->avg('value') ?: 0,
            'min' => $query->min('value') ?: 0,
            'max' => $query->max('value') ?: 0,
            'count' => $query->count(),
            default => $query->sum('value')
        };
    }

    /**
     * Get time series data for metric.
     */
    public static function getTimeSeries(
        string $metricName,
        string $interval = 'day',
        ?int $tenantId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate = $startDate ?: now()->subDays(30);
        $endDate = $endDate ?: now();

        $query = static::byMetric($metricName)
                       ->byDateRange($startDate, $endDate);

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        $dateFormat = match($interval) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        return $query->selectRaw("DATE_FORMAT(recorded_at, '{$dateFormat}') as period")
                     ->selectRaw('SUM(value) as total')
                     ->selectRaw('AVG(value) as average')
                     ->selectRaw('COUNT(*) as count')
                     ->groupBy('period')
                     ->orderBy('period')
                     ->get()
                     ->toArray();
    }

    /**
     * Record a metric value.
     */
    public static function record(
        string $metricName,
        string $metricType,
        string $category,
        float $value,
        array $dimensions = [],
        ?int $tenantId = null,
        ?Carbon $recordedAt = null
    ): self {
        return static::create([
            'metric_name' => $metricName,
            'metric_type' => $metricType,
            'category' => $category,
            'value' => $value,
            'dimensions' => $dimensions,
            'tenant_id' => $tenantId,
            'recorded_at' => $recordedAt ?: now()
        ]);
    }

    /**
     * Record user metrics.
     */
    public static function recordUserMetrics(): void
    {
        // Total users
        $totalUsers = User::count();
        static::record('total_users', static::TYPE_GAUGE, static::CATEGORY_USER, $totalUsers);

        // Active users (last 30 days)
        $activeUsers = User::where('updated_at', '>=', now()->subDays(30))->count();
        static::record('active_users_30d', static::TYPE_GAUGE, static::CATEGORY_USER, $activeUsers);

        // New registrations (today)
        $newRegistrations = User::whereDate('created_at', now())->count();
        static::record('new_registrations', static::TYPE_COUNTER, static::CATEGORY_USER, $newRegistrations);

        // Per tenant metrics
        foreach (Tenant::all() as $tenant) {
            $tenantUsers = $tenant->users()->count();
            static::record('tenant_users', static::TYPE_GAUGE, static::CATEGORY_USER, $tenantUsers, 
                         ['tenant_name' => $tenant->name], $tenant->id);

            $tenantActiveUsers = $tenant->users()->where('updated_at', '>=', now()->subDays(30))->count();
            static::record('tenant_active_users', static::TYPE_GAUGE, static::CATEGORY_USER, $tenantActiveUsers,
                         ['tenant_name' => $tenant->name], $tenant->id);
        }
    }

    /**
     * Record module metrics.
     */
    public static function recordModuleMetrics(): void
    {
        // Total modules
        $totalModules = Module::count();
        static::record('total_modules', static::TYPE_GAUGE, static::CATEGORY_MODULE, $totalModules);

        // Active modules
        $activeModules = Module::active()->count();
        static::record('active_modules', static::TYPE_GAUGE, static::CATEGORY_MODULE, $activeModules);

        // Module installations
        $totalInstallations = ModuleInstallation::count();
        static::record('total_module_installations', static::TYPE_GAUGE, static::CATEGORY_MODULE, $totalInstallations);

        // Active installations
        $activeInstallations = ModuleInstallation::where('status', ModuleInstallation::STATUS_ACTIVE)->count();
        static::record('active_module_installations', static::TYPE_GAUGE, static::CATEGORY_MODULE, $activeInstallations);

        // Popular modules (by installation count)
        $popularModules = Module::withCount('installations')
                                ->orderBy('installations_count', 'desc')
                                ->take(10)
                                ->get();

        foreach ($popularModules as $module) {
            static::record('module_installations', static::TYPE_GAUGE, static::CATEGORY_MODULE, 
                         $module->installations_count, [
                             'module_name' => $module->name,
                             'module_category' => $module->category
                         ]);
        }

        // Module ratings
        $avgRating = Module::whereNotNull('rating')->avg('rating');
        if ($avgRating) {
            static::record('average_module_rating', static::TYPE_GAUGE, static::CATEGORY_MODULE, $avgRating);
        }
    }

    /**
     * Record revenue metrics.
     */
    public static function recordRevenueMetrics(): void
    {
        // This would integrate with subscription/payment data
        // For now, we'll create placeholder metrics based on module prices

        $paidModules = Module::where('price', '>', 0)->get();
        $totalPotentialRevenue = $paidModules->sum(function ($module) {
            return ($module->price / 100) * $module->installations()->count();
        });

        static::record('potential_module_revenue', static::TYPE_GAUGE, static::CATEGORY_REVENUE, $totalPotentialRevenue);

        // Revenue by module category
        $revenueByCategory = $paidModules->groupBy('category')->map(function ($modules, $category) {
            return $modules->sum(function ($module) {
                return ($module->price / 100) * $module->installations()->count();
            });
        });

        foreach ($revenueByCategory as $category => $revenue) {
            static::record('category_revenue', static::TYPE_GAUGE, static::CATEGORY_REVENUE, $revenue, [
                'category' => $category
            ]);
        }
    }

    /**
     * Record performance metrics.
     */
    public static function recordPerformanceMetrics(): void
    {
        // System performance metrics (placeholder)
        static::record('avg_response_time', static::TYPE_GAUGE, static::CATEGORY_PERFORMANCE, rand(100, 500));
        static::record('error_rate', static::TYPE_PERCENTAGE, static::CATEGORY_PERFORMANCE, rand(0, 2) / 100);
        static::record('uptime_percentage', static::TYPE_PERCENTAGE, static::CATEGORY_PERFORMANCE, 99.9);

        // Module performance metrics
        $installations = ModuleInstallation::where('status', ModuleInstallation::STATUS_ACTIVE)
                                          ->whereNotNull('performance_data')
                                          ->get();

        foreach ($installations as $installation) {
            if (!empty($installation->performance_data)) {
                $latestMetrics = collect($installation->performance_data)->last();
                if (isset($latestMetrics['metrics'])) {
                    $metrics = $latestMetrics['metrics'];
                    
                    static::record('module_response_time', static::TYPE_GAUGE, static::CATEGORY_PERFORMANCE, 
                                 $metrics['response_time'] ?? 0, [
                                     'module_name' => $installation->module->name,
                                     'tenant_id' => $installation->tenant_id
                                 ], $installation->tenant_id);
                }
            }
        }
    }

    /**
     * Get available metric names by category.
     */
    public static function getAvailableMetrics(): array
    {
        return [
            static::CATEGORY_USER => [
                'total_users' => 'Total Users',
                'active_users_30d' => 'Active Users (30 days)',
                'new_registrations' => 'New Registrations',
                'tenant_users' => 'Users per Tenant',
                'tenant_active_users' => 'Active Users per Tenant'
            ],
            static::CATEGORY_MODULE => [
                'total_modules' => 'Total Modules',
                'active_modules' => 'Active Modules',
                'total_module_installations' => 'Total Installations',
                'active_module_installations' => 'Active Installations',
                'module_installations' => 'Installations by Module',
                'average_module_rating' => 'Average Module Rating'
            ],
            static::CATEGORY_REVENUE => [
                'potential_module_revenue' => 'Potential Module Revenue',
                'category_revenue' => 'Revenue by Category'
            ],
            static::CATEGORY_PERFORMANCE => [
                'avg_response_time' => 'Average Response Time',
                'error_rate' => 'Error Rate',
                'uptime_percentage' => 'Uptime Percentage',
                'module_response_time' => 'Module Response Time'
            ]
        ];
    }

    /**
     * Format metric value for display.
     */
    public function getFormattedValue(): string
    {
        $value = $this->value;

        return match($this->metric_type) {
            static::TYPE_PERCENTAGE => number_format($value * 100, 2) . '%',
            static::TYPE_RATE => number_format($value, 2) . '/s',
            default => number_format($value, 2)
        };
    }
}