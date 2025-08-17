<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature',
        'metric',
        'alert_type',
        'threshold_percentage',
        'current_usage',
        'limit_value',
        'is_sent',
        'sent_at',
        'notification_data',
    ];

    protected $casts = [
        'threshold_percentage' => 'decimal:2',
        'current_usage' => 'decimal:2',
        'limit_value' => 'decimal:2',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
        'notification_data' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope for unsent alerts
     */
    public function scopeUnsent($query)
    {
        return $query->where('is_sent', false);
    }

    /**
     * Scope for specific alert type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Mark alert as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }

    /**
     * Get formatted alert message
     */
    public function getFormattedMessage(): string
    {
        $percentage = $this->current_usage / $this->limit_value * 100;
        $featureName = ucwords(str_replace('_', ' ', $this->feature));
        $metricName = ucwords(str_replace('_', ' ', $this->metric));

        return match ($this->alert_type) {
            'warning' => "You've used {$percentage}% of your {$featureName} {$metricName} limit.",
            'limit_reached' => "You've reached 100% of your {$featureName} {$metricName} limit.",
            'limit_exceeded' => "You've exceeded your {$featureName} {$metricName} limit.",
            default => "Usage alert for {$featureName} {$metricName}."
        };
    }
}