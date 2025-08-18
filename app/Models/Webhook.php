<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
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
        'metadata',
        'retry_count',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'is_active' => 'boolean',
            'retry_attempts' => 'integer',
            'timeout' => 'integer',
            'failure_count' => 'integer',
            'last_triggered_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'metadata' => 'array',
            'retry_count' => 'integer',
        ];
    }

    protected $hidden = [
        'secret',
    ];

    /**
     * Webhook status constants.
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_FAILED = 'failed';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Available webhook events.
     */
    const EVENTS = [
        // User events
        'user.created',
        'user.updated',
        'user.deleted',
        
        // Tenant events
        'tenant.created',
        'tenant.updated',
        'tenant.deleted',
        'tenant.suspended',
        'tenant.activated',
        
        // Subscription events
        'subscription.created',
        'subscription.updated',
        'subscription.cancelled',
        'subscription.resumed',
        'subscription.paused',
        'subscription.expired',
        'subscription.trial_ended',
        'subscription.payment_succeeded',
        'subscription.payment_failed',
        
        // Usage events
        'usage.limit_warning',
        'usage.limit_exceeded',
        'usage.reset',
        
        // Feature events
        'feature.access_granted',
        'feature.access_denied',
        'feature.limit_reached',
        
        // Security events
        'security.login_failed',
        'security.password_changed',
        'security.two_factor_enabled',
        'security.suspicious_activity',
        
        // System events
        'system.maintenance_start',
        'system.maintenance_end',
        'system.upgrade_available',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(64);
            }
        });
    }

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
     * Get the webhook deliveries for this webhook.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Check if webhook is configured for a specific event.
     */
    public function handlesEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Check if webhook should receive specific event.
     */
    public function shouldReceiveEvent(string $event): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // If no events specified, receive all
        if (empty($this->events)) {
            return true;
        }

        // Check for exact match
        if (in_array($event, $this->events)) {
            return true;
        }

        // Check for wildcard matches
        foreach ($this->events as $subscribedEvent) {
            if (Str::is($subscribedEvent, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deliver webhook payload.
     */
    public function deliver(string $event, array $payload, array $options = []): ?WebhookDelivery
    {
        if (!$this->shouldReceiveEvent($event)) {
            return null;
        }

        $delivery = $this->deliveries()->create([
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $this->attemptDelivery($delivery, $options);

        return $delivery;
    }

    /**
     * Attempt to deliver webhook.
     */
    public function attemptDelivery($delivery, array $options = []): bool
    {
        $delivery->increment('attempts');
        $delivery->update(['status' => 'sending']);

        try {
            $headers = array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-SaaS-Webhook/1.0',
                'X-Webhook-Event' => $delivery->event,
                'X-Webhook-Signature' => $this->generateSignature($delivery->payload),
                'X-Webhook-Timestamp' => now()->timestamp,
                'X-Webhook-ID' => $delivery->id,
            ], $this->headers ?? []);

            $response = Http::timeout($this->timeout ?? 30)
                ->withHeaders($headers)
                ->retry($options['immediate_retries'] ?? 0, 1000)
                ->post($this->url, [
                    'event' => $delivery->event,
                    'data' => $delivery->payload,
                    'webhook' => [
                        'id' => $this->id,
                        'tenant_id' => $this->tenant_id,
                    ],
                    'timestamp' => now()->toISOString(),
                ]);

            $delivery->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'response_status' => $response->status(),
                'response_body' => $response->body(),
                'response_headers' => $response->headers(),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $this->markAsTriggered();
                $this->resetFailures();
            } else {
                $this->markAsFailed("HTTP {$response->status()}");
            }

            return $response->successful();

        } catch (\Exception $e) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Generate webhook signature.
     */
    public function generateSignature(array $payload): string
    {
        $data = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'sha256=' . hash_hmac('sha256', $data, $this->secret);
    }

    /**
     * Verify webhook signature.
     */
    public function verifySignature(string $signature, array $payload): bool
    {
        $expectedSignature = $this->generateSignature($payload);
        return hash_equals($expectedSignature, $signature);
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

        // Disable webhook after too many failures
        if ($this->shouldBeDisabled()) {
            $this->update(['is_active' => false]);
        }
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

    /**
     * Test webhook with a ping event.
     */
    public function ping(): ?WebhookDelivery
    {
        return $this->deliver('webhook.ping', [
            'message' => 'This is a test webhook delivery',
            'webhook_id' => $this->id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get webhook statistics.
     */
    public function getStats(int $days = 30): array
    {
        $deliveries = $this->deliveries()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $totalDeliveries = $deliveries->count();
        $successfulDeliveries = $deliveries->where('status', 'success')->count();
        $failedDeliveries = $deliveries->where('status', 'failed')->count();

        return [
            'total_deliveries' => $totalDeliveries,
            'successful_deliveries' => $successfulDeliveries,
            'failed_deliveries' => $failedDeliveries,
            'success_rate' => $totalDeliveries > 0 ? round(($successfulDeliveries / $totalDeliveries) * 100, 2) : 0,
        ];
    }

    /**
     * Scope to get active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get webhooks for specific event.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where(function ($q) use ($event) {
            $q->whereJsonContains('events', $event)
              ->orWhereNull('events')
              ->orWhere('events', '[]');
        });
    }

    /**
     * Get all available events.
     */
    public static function getAvailableEvents(): array
    {
        return self::EVENTS;
    }

    /**
     * Get events grouped by category.
     */
    public static function getEventsByCategory(): array
    {
        $events = [];
        
        foreach (self::EVENTS as $event) {
            $parts = explode('.', $event);
            $category = $parts[0];
            
            if (!isset($events[$category])) {
                $events[$category] = [];
            }
            
            $events[$category][] = $event;
        }
        
        return $events;
    }
}