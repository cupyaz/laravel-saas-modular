<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'headers',
        'retry_attempts',
        'timeout',
        'last_triggered_at',
        'failure_count',
        'last_failure_at',
        'last_failure_reason',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'retry_attempts' => 'integer',
        'timeout' => 'integer',
        'failure_count' => 'integer',
        'last_triggered_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Get the user that owns the webhook.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant that owns the webhook.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if webhook is configured for a specific event.
     */
    public function handlesEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Mark webhook as successfully triggered.
     */
    public function markAsTriggered(): void
    {
        $this->update([
            'last_triggered_at' => now(),
        ]);
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->increment('failure_count');
        $this->update([
            'last_failure_at' => now(),
            'last_failure_reason' => $reason,
        ]);
    }

    /**
     * Reset failure count.
     */
    public function resetFailures(): void
    {
        $this->update([
            'failure_count' => 0,
            'last_failure_at' => null,
            'last_failure_reason' => null,
        ]);
    }

    /**
     * Check if webhook should be disabled due to failures.
     */
    public function shouldBeDisabled(): bool
    {
        return $this->failure_count >= 10; // Disable after 10 consecutive failures
    }
}