<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature',
        'metric',
        'period',
        'period_date',
        'total_usage',
        'limit_value',
        'percentage_used',
        'limit_exceeded',
        'last_updated_at',
    ];

    protected $casts = [
        'period_date' => 'date',
        'total_usage' => 'decimal:2',
        'limit_value' => 'decimal:2',
        'percentage_used' => 'decimal:2',
        'limit_exceeded' => 'boolean',
        'last_updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if usage is approaching limit (>= 80%)
     */
    public function isApproachingLimit(): bool
    {
        if ($this->limit_value == -1) {
            return false; // Unlimited
        }

        return $this->percentage_used >= 80.0;
    }

    /**
     * Check if limit is exceeded
     */
    public function isLimitExceeded(): bool
    {
        return $this->limit_exceeded;
    }

    /**
     * Get remaining usage amount
     */
    public function getRemainingUsage(): float
    {
        if ($this->limit_value == -1) {
            return PHP_FLOAT_MAX; // Unlimited
        }

        return max(0, $this->limit_value - $this->total_usage);
    }

    /**
     * Scope for current period summaries
     */
    public function scopeCurrentPeriod($query, string $period = 'monthly')
    {
        $date = match ($period) {
            'daily' => now()->format('Y-m-d'),
            'weekly' => now()->startOfWeek()->format('Y-m-d'),
            'monthly' => now()->format('Y-m-01'),
            'yearly' => now()->format('Y-01-01'),
            default => now()->format('Y-m-01'),
        };

        return $query->where('period_date', $date)->where('period', $period);
    }

    /**
     * Scope for approaching limits
     */
    public function scopeApproachingLimit($query)
    {
        return $query->where('percentage_used', '>=', 80.0)
                    ->where('limit_value', '>', 0);
    }

    /**
     * Scope for exceeded limits
     */
    public function scopeExceededLimit($query)
    {
        return $query->where('limit_exceeded', true);
    }
}