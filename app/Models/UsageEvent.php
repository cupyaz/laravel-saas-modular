<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature',
        'metric',
        'amount',
        'event_type',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for specific event type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for increment events
     */
    public function scopeIncrements($query)
    {
        return $query->where('event_type', 'increment');
    }

    /**
     * Scope for decrement events
     */
    public function scopeDecrements($query)
    {
        return $query->where('event_type', 'decrement');
    }
}