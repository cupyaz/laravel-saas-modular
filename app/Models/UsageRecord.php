<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature',
        'metric',
        'amount',
        'period',
        'period_date',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_date' => 'date',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by current period
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
     * Scope to filter by feature and metric
     */
    public function scopeForFeature($query, string $feature, ?string $metric = null)
    {
        $query = $query->where('feature', $feature);
        
        if ($metric) {
            $query = $query->where('metric', $metric);
        }
        
        return $query;
    }
}