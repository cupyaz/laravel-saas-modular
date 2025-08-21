<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'user_id',
        'template_name',
        'channel',
        'status',
        'recipient',
        'payload',
        'response',
        'error_message',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'metadata',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_OPENED = 'opened';
    public const STATUS_CLICKED = 'clicked';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /**
     * Get the associated notification.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(\Illuminate\Notifications\DatabaseNotification::class, 'notification_id');
    }

    /**
     * Get the user this log belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the notification template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_name', 'name');
    }

    /**
     * Scope for specific status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for successful deliveries.
     */
    public function scopeDelivered($query)
    {
        return $query->whereIn('status', [self::STATUS_DELIVERED, self::STATUS_OPENED, self::STATUS_CLICKED]);
    }

    /**
     * Scope for failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_BOUNCED]);
    }

    /**
     * Scope for specific channel.
     */
    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for specific template.
     */
    public function scopeByTemplate($query, $templateName)
    {
        return $query->where('template_name', $templateName);
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope for recent logs.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if notification was successfully delivered.
     */
    public function wasDelivered(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_OPENED,
            self::STATUS_CLICKED
        ]);
    }

    /**
     * Check if notification failed.
     */
    public function failed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_BOUNCED]);
    }

    /**
     * Check if notification was opened.
     */
    public function wasOpened(): bool
    {
        return in_array($this->status, [self::STATUS_OPENED, self::STATUS_CLICKED]);
    }

    /**
     * Check if notification was clicked.
     */
    public function wasClicked(): bool
    {
        return $this->status === self::STATUS_CLICKED;
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(array $response = null): self
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'response' => $response,
        ]);

        return $this;
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered(): self
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage, array $response = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'response' => $response,
        ]);

        return $this;
    }

    /**
     * Mark as bounced.
     */
    public function markAsBounced(string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_BOUNCED,
            'error_message' => $reason,
        ]);

        return $this;
    }

    /**
     * Mark as opened.
     */
    public function markAsOpened(): self
    {
        $this->update([
            'status' => self::STATUS_OPENED,
            'opened_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as clicked.
     */
    public function markAsClicked(): self
    {
        $this->update([
            'status' => self::STATUS_CLICKED,
            'clicked_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get delivery time in seconds.
     */
    public function getDeliveryTimeAttribute(): ?int
    {
        if (!$this->sent_at || !$this->delivered_at) {
            return null;
        }

        return $this->sent_at->diffInSeconds($this->delivered_at);
    }

    /**
     * Get time to open in seconds.
     */
    public function getTimeToOpenAttribute(): ?int
    {
        if (!$this->delivered_at || !$this->opened_at) {
            return null;
        }

        return $this->delivered_at->diffInSeconds($this->opened_at);
    }

    /**
     * Get time to click in seconds.
     */
    public function getTimeToClickAttribute(): ?int
    {
        if (!$this->opened_at || !$this->clicked_at) {
            return null;
        }

        return $this->opened_at->diffInSeconds($this->clicked_at);
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_BOUNCED => 'Bounced',
            self::STATUS_OPENED => 'Opened',
            self::STATUS_CLICKED => 'Clicked',
            self::STATUS_UNSUBSCRIBED => 'Unsubscribed',
        ];
    }

    /**
     * Create log entry for notification.
     */
    public static function createForNotification(
        ?string $notificationId,
        int $userId,
        string $templateName,
        string $channel,
        string $recipient,
        array $payload = []
    ): self {
        return self::create([
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'template_name' => $templateName,
            'channel' => $channel,
            'recipient' => $recipient,
            'payload' => $payload,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Get delivery statistics for a time period.
     */
    public static function getDeliveryStats(Carbon $start, Carbon $end): array
    {
        $logs = self::whereBetween('created_at', [$start, $end]);

        $total = $logs->count();
        $delivered = $logs->clone()->delivered()->count();
        $failed = $logs->clone()->failed()->count();
        $opened = $logs->clone()->where('status', self::STATUS_OPENED)->count();
        $clicked = $logs->clone()->where('status', self::STATUS_CLICKED)->count();

        return [
            'total_sent' => $total,
            'delivered' => $delivered,
            'failed' => $failed,
            'bounced' => $logs->clone()->where('status', self::STATUS_BOUNCED)->count(),
            'opened' => $opened,
            'clicked' => $clicked,
            'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
            'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
            'click_rate' => $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get channel statistics.
     */
    public static function getChannelStats(Carbon $start, Carbon $end): array
    {
        return self::whereBetween('created_at', [$start, $end])
            ->selectRaw('channel, status, COUNT(*) as count')
            ->groupBy(['channel', 'status'])
            ->get()
            ->groupBy('channel')
            ->map(function ($channelLogs) {
                $total = $channelLogs->sum('count');
                $delivered = $channelLogs->where('status', self::STATUS_DELIVERED)->sum('count');
                
                return [
                    'total' => $total,
                    'delivered' => $delivered,
                    'failed' => $channelLogs->whereIn('status', [self::STATUS_FAILED, self::STATUS_BOUNCED])->sum('count'),
                    'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
                    'status_breakdown' => $channelLogs->pluck('count', 'status')->toArray(),
                ];
            })
            ->toArray();
    }

    /**
     * Get template performance statistics.
     */
    public static function getTemplateStats(Carbon $start, Carbon $end): array
    {
        return self::whereBetween('created_at', [$start, $end])
            ->selectRaw('template_name, status, COUNT(*) as count')
            ->groupBy(['template_name', 'status'])
            ->get()
            ->groupBy('template_name')
            ->map(function ($templateLogs) {
                $total = $templateLogs->sum('count');
                $delivered = $templateLogs->where('status', self::STATUS_DELIVERED)->sum('count');
                $opened = $templateLogs->where('status', self::STATUS_OPENED)->sum('count');
                $clicked = $templateLogs->where('status', self::STATUS_CLICKED)->sum('count');
                
                return [
                    'total' => $total,
                    'delivered' => $delivered,
                    'opened' => $opened,
                    'clicked' => $clicked,
                    'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
                    'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
                    'click_rate' => $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Clean up old logs.
     */
    public static function cleanup(int $daysToKeep = 90): int
    {
        return self::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }

    /**
     * Get status color for display.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '#6c757d',
            self::STATUS_SENT => '#17a2b8',
            self::STATUS_DELIVERED => '#28a745',
            self::STATUS_OPENED => '#007bff',
            self::STATUS_CLICKED => '#20c997',
            self::STATUS_FAILED, self::STATUS_BOUNCED => '#dc3545',
            self::STATUS_UNSUBSCRIBED => '#ffc107',
            default => '#6c757d',
        };
    }
}