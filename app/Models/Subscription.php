<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Subscription as CashierSubscription;

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
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'ends_at',
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
            'quantity' => 'integer',
        ];
    }

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
     * Check if the subscription is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            'active',
            'trialing',
            'past_due',
        ]);
    }

    /**
     * Check if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === 'trialing' && 
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
        return $this->status === 'canceled';
    }

    /**
     * Check if the subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
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
        return $query->where('ends_at', '<=', now());
    }
}