<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Subscription as CashierSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\SubscriptionStateChanged;
use App\Mail\SubscriptionCancelled;
use App\Mail\SubscriptionPaused;
use App\Mail\SubscriptionResumed;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'stripe_subscription_id',
        'status',
        'internal_status',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'ends_at',
        'paused_at',
        'grace_period_ends_at',
        'cancellation_reason',
        'cancellation_feedback',
        'retention_offer_shown',
        'retention_offer_shown_at',
        'metadata',
        'quantity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'retention_offer_shown_at' => 'datetime',
            'cancellation_feedback' => 'array',
            'metadata' => 'array',
            'quantity' => 'integer',
            'retention_offer_shown' => 'boolean',
        ];
    }

    /**
     * Subscription states for internal state machine
     */
    public const STATE_ACTIVE = 'active';
    public const STATE_PAUSED = 'paused';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_EXPIRED = 'expired';
    public const STATE_GRACE_PERIOD = 'grace_period';
    public const STATE_TRIAL = 'trialing';
    public const STATE_PAST_DUE = 'past_due';

    /**
     * Valid state transitions
     */
    protected static array $validTransitions = [
        self::STATE_ACTIVE => [self::STATE_PAUSED, self::STATE_CANCELLED, self::STATE_EXPIRED, self::STATE_PAST_DUE],
        self::STATE_TRIAL => [self::STATE_ACTIVE, self::STATE_CANCELLED, self::STATE_EXPIRED],
        self::STATE_PAUSED => [self::STATE_ACTIVE, self::STATE_CANCELLED, self::STATE_EXPIRED],
        self::STATE_CANCELLED => [self::STATE_GRACE_PERIOD, self::STATE_EXPIRED],
        self::STATE_GRACE_PERIOD => [self::STATE_ACTIVE, self::STATE_EXPIRED],
        self::STATE_PAST_DUE => [self::STATE_ACTIVE, self::STATE_CANCELLED, self::STATE_EXPIRED],
        self::STATE_EXPIRED => [], // Terminal state
    ];

    /**
     * Get the tenant that owns the subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the subscription items for this subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Get the invoices for this subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the retention offers for this subscription.
     */
    public function retentionOffers(): HasMany
    {
        return $this->hasMany(RetentionOffer::class);
    }

    /**
     * Check if the subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->internal_status, [
            self::STATE_ACTIVE,
            self::STATE_TRIAL,
        ]) && !$this->isPaused();
    }

    /**
     * Check if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->internal_status === self::STATE_TRIAL && 
               $this->trial_ends_at && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the subscription has expired.
     */
    public function hasExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Check if the subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return in_array($this->internal_status, [self::STATE_CANCELLED, self::STATE_GRACE_PERIOD]);
    }

    /**
     * Check if the subscription is paused.
     */
    public function isPaused(): bool
    {
        return $this->internal_status === self::STATE_PAUSED;
    }

    /**
     * Check if the subscription is in grace period.
     */
    public function inGracePeriod(): bool
    {
        return $this->internal_status === self::STATE_GRACE_PERIOD && 
               $this->grace_period_ends_at && 
               $this->grace_period_ends_at->isFuture();
    }

    /**
     * Check if the subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->internal_status === self::STATE_PAST_DUE;
    }

    /**
     * Get the subscription's remaining trial days.
     */
    public function getRemainingTrialDays(): int
    {
        if (!$this->onTrial()) {
            return 0;
        }

        return $this->trial_ends_at->diffInDays(now());
    }

    /**
     * Get the subscription's next billing date.
     */
    public function getNextBillingDate(): ?\Carbon\Carbon
    {
        if (!$this->isActive()) {
            return null;
        }

        return $this->current_period_end;
    }

    /**
     * Get the formatted price for this subscription.
     */
    public function getFormattedPriceAttribute(): string
    {
        return $this->plan->getFormattedPriceAttribute();
    }

    /**
     * Calculate proration amount for upgrading to a new plan.
     */
    public function calculateProration(Plan $newPlan): array
    {
        if (!$this->isActive() || !$this->current_period_end) {
            return ['amount' => 0, 'description' => 'No proration needed'];
        }

        $daysRemaining = now()->diffInDays($this->current_period_end);
        $totalDays = $this->current_period_start->diffInDays($this->current_period_end);
        
        if ($totalDays <= 0) {
            return ['amount' => 0, 'description' => 'No proration needed'];
        }

        $currentPlanDaily = $this->plan->price / $totalDays;
        $newPlanDaily = $newPlan->price / $totalDays;
        
        $prorationAmount = ($newPlanDaily - $currentPlanDaily) * $daysRemaining;
        
        return [
            'amount' => round($prorationAmount, 2),
            'description' => sprintf(
                'Proration for %d days remaining in billing period',
                $daysRemaining
            ),
            'days_remaining' => $daysRemaining,
            'current_plan_daily' => round($currentPlanDaily, 2),
            'new_plan_daily' => round($newPlanDaily, 2),
        ];
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trialing', 'past_due']);
    }

    /**
     * Scope to get expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('internal_status', self::STATE_EXPIRED);
    }

    /**
     * Scope to get paused subscriptions.
     */
    public function scopePaused($query)
    {
        return $query->where('internal_status', self::STATE_PAUSED);
    }

    /**
     * Scope to get subscriptions in grace period.
     */
    public function scopeGracePeriod($query)
    {
        return $query->where('internal_status', self::STATE_GRACE_PERIOD);
    }

    // STATE MACHINE METHODS

    /**
     * Transition to a new state with validation.
     */
    public function transitionTo(string $newState, array $data = []): bool
    {
        if (!$this->canTransitionTo($newState)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$this->internal_status} to {$newState}"
            );
        }

        $oldState = $this->internal_status;
        $this->internal_status = $newState;
        
        // Set specific fields based on state
        switch ($newState) {
            case self::STATE_PAUSED:
                $this->paused_at = now();
                break;
                
            case self::STATE_CANCELLED:
                $this->ends_at = now()->addDays(30); // Grace period
                $this->grace_period_ends_at = $this->ends_at;
                if (isset($data['reason'])) {
                    $this->cancellation_reason = $data['reason'];
                }
                if (isset($data['feedback'])) {
                    $this->cancellation_feedback = $data['feedback'];
                }
                break;
                
            case self::STATE_GRACE_PERIOD:
                $this->grace_period_ends_at = now()->addDays(30);
                break;
                
            case self::STATE_ACTIVE:
                if ($oldState === self::STATE_PAUSED) {
                    $this->paused_at = null;
                }
                if (in_array($oldState, [self::STATE_CANCELLED, self::STATE_GRACE_PERIOD])) {
                    $this->ends_at = null;
                    $this->grace_period_ends_at = null;
                    $this->cancellation_reason = null;
                    $this->cancellation_feedback = null;
                }
                break;
                
            case self::STATE_EXPIRED:
                $this->ends_at = now();
                break;
        }

        $saved = $this->save();
        
        if ($saved) {
            $this->sendStateChangeNotification($oldState, $newState);
        }
        
        return $saved;
    }

    /**
     * Check if subscription can transition to a new state.
     */
    public function canTransitionTo(string $newState): bool
    {
        return in_array($newState, static::$validTransitions[$this->internal_status] ?? []);
    }

    /**
     * Pause the subscription.
     */
    public function pause(): bool
    {
        return $this->transitionTo(self::STATE_PAUSED);
    }

    /**
     * Resume the subscription from paused state.
     */
    public function resume(): bool
    {
        if (!$this->isPaused()) {
            return false;
        }
        
        return $this->transitionTo(self::STATE_ACTIVE);
    }

    /**
     * Cancel the subscription with optional reason and feedback.
     */
    public function cancel(string $reason = null, array $feedback = null): bool
    {
        $data = [];
        if ($reason) $data['reason'] = $reason;
        if ($feedback) $data['feedback'] = $feedback;
        
        return $this->transitionTo(self::STATE_CANCELLED, $data);
    }

    /**
     * Reactivate a cancelled subscription.
     */
    public function reactivate(): bool
    {
        if (!$this->isCancelled()) {
            return false;
        }
        
        return $this->transitionTo(self::STATE_ACTIVE);
    }

    /**
     * Get days remaining in grace period.
     */
    public function getGracePeriodDaysRemaining(): int
    {
        if (!$this->inGracePeriod()) {
            return 0;
        }
        
        return $this->grace_period_ends_at->diffInDays(now());
    }

    /**
     * Check if subscription should show retention offer.
     */
    public function shouldShowRetentionOffer(): bool
    {
        return !$this->retention_offer_shown && 
               $this->isCancelled() && 
               $this->inGracePeriod();
    }

    /**
     * Mark retention offer as shown.
     */
    public function markRetentionOfferShown(): bool
    {
        $this->retention_offer_shown = true;
        $this->retention_offer_shown_at = now();
        return $this->save();
    }

    /**
     * Send email notification for state changes.
     */
    protected function sendStateChangeNotification(string $oldState, string $newState): void
    {
        try {
            $user = $this->tenant->user;
            
            switch ($newState) {
                case self::STATE_CANCELLED:
                    Mail::to($user)->send(new SubscriptionCancelled($this));
                    break;
                    
                case self::STATE_PAUSED:
                    Mail::to($user)->send(new SubscriptionPaused($this));
                    break;
                    
                case self::STATE_ACTIVE:
                    if ($oldState === self::STATE_PAUSED) {
                        Mail::to($user)->send(new SubscriptionResumed($this));
                    } elseif (in_array($oldState, [self::STATE_CANCELLED, self::STATE_GRACE_PERIOD])) {
                        Mail::to($user)->send(new SubscriptionStateChanged($this, $oldState, $newState));
                    }
                    break;
                    
                default:
                    Mail::to($user)->send(new SubscriptionStateChanged($this, $oldState, $newState));
                    break;
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send subscription state change notification', [
                'subscription_id' => $this->id,
                'old_state' => $oldState,
                'new_state' => $newState,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get human-readable status.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->internal_status) {
            self::STATE_ACTIVE => 'Active',
            self::STATE_TRIAL => 'Trial',
            self::STATE_PAUSED => 'Paused',
            self::STATE_CANCELLED => 'Cancelled',
            self::STATE_GRACE_PERIOD => 'Grace Period',
            self::STATE_EXPIRED => 'Expired',
            self::STATE_PAST_DUE => 'Past Due',
            default => ucfirst($this->internal_status),
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->internal_status) {
            self::STATE_ACTIVE => 'green',
            self::STATE_TRIAL => 'blue',
            self::STATE_PAUSED => 'yellow',
            self::STATE_CANCELLED => 'red',
            self::STATE_GRACE_PERIOD => 'orange',
            self::STATE_EXPIRED => 'gray',
            self::STATE_PAST_DUE => 'red',
            default => 'gray',
        };
    }
}