<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'status',
        'attempts',
        'response_status',
        'response_body',
        'response_headers',
        'error_message',
        'delivered_at',
        'response_time',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_headers' => 'array',
            'attempts' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
            'response_time' => 'integer',
        ];
    }

    /**
     * Delivery status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENDING = 'sending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_FAILED_PERMANENT = 'failed_permanent';

    /**
     * Get the webhook that owns this delivery.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Check if delivery was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if delivery failed.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_FAILED_PERMANENT]);
    }

    /**
     * Check if delivery can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->attempts < 5;
    }

    /**
     * Get delivery duration in milliseconds.
     */
    public function getDurationMs(): ?int
    {
        return $this->response_time;
    }

    /**
     * Get human-readable status.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENDING => 'Sending',
            self::STATUS_SUCCESS => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_FAILED_PERMANENT => 'Failed (Permanent)',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'blue',
            self::STATUS_SENDING => 'yellow',
            self::STATUS_SUCCESS => 'green',
            self::STATUS_FAILED => 'orange',
            self::STATUS_FAILED_PERMANENT => 'red',
            default => 'gray',
        };
    }

    /**
     * Scope for successful deliveries.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope for failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_FAILED_PERMANENT]);
    }

    /**
     * Scope for retryable deliveries.
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', self::STATUS_FAILED)->where('attempts', '<', 5);
    }

    /**
     * Scope for recent deliveries.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}