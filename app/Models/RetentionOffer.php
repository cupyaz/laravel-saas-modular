<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RetentionOffer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'offer_type',
        'discount_type',
        'discount_value',
        'free_months',
        'downgrade_plan_id',
        'offer_description',
        'valid_until',
        'is_accepted',
        'accepted_at',
        'expires_at',
        'terms',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_until' => 'datetime',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_accepted' => 'boolean',
            'discount_value' => 'decimal:2',
            'free_months' => 'integer',
            'terms' => 'array',
            'metadata' => 'array',
        ];
    }

    // Offer types
    public const TYPE_DISCOUNT = 'discount';
    public const TYPE_FREE_MONTHS = 'free_months';
    public const TYPE_PLAN_DOWNGRADE = 'plan_downgrade';

    // Discount types
    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED_AMOUNT = 'fixed_amount';

    /**
     * Get the subscription that owns the retention offer.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the tenant that owns the retention offer.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the downgrade plan if applicable.
     */
    public function downgradePlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'downgrade_plan_id');
    }

    /**
     * Check if the offer is still valid.
     */
    public function isValid(): bool
    {
        return !$this->is_accepted && 
               $this->valid_until && 
               $this->valid_until->isFuture();
    }

    /**
     * Check if the offer has expired.
     */
    public function hasExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    /**
     * Accept the retention offer.
     */
    public function accept(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $this->is_accepted = true;
        $this->accepted_at = now();
        
        return $this->save();
    }

    /**
     * Get the discount amount for the current subscription.
     */
    public function getDiscountAmount(): float
    {
        if ($this->offer_type !== self::TYPE_DISCOUNT) {
            return 0.0;
        }

        $subscription = $this->subscription;
        $planPrice = $subscription->plan->price;

        return match ($this->discount_type) {
            self::DISCOUNT_PERCENTAGE => $planPrice * ($this->discount_value / 100),
            self::DISCOUNT_FIXED_AMOUNT => min($this->discount_value, $planPrice),
            default => 0.0,
        };
    }

    /**
     * Get the final price after discount.
     */
    public function getFinalPrice(): float
    {
        $subscription = $this->subscription;
        $originalPrice = $subscription->plan->price;
        
        return max(0, $originalPrice - $this->getDiscountAmount());
    }

    /**
     * Get formatted discount display.
     */
    public function getFormattedDiscountAttribute(): string
    {
        return match ($this->offer_type) {
            self::TYPE_DISCOUNT => $this->formatDiscountDisplay(),
            self::TYPE_FREE_MONTHS => "{$this->free_months} free months",
            self::TYPE_PLAN_DOWNGRADE => "Downgrade to {$this->downgradePlan->name}",
            default => 'Special offer',
        };
    }

    /**
     * Format discount display based on type.
     */
    protected function formatDiscountDisplay(): string
    {
        return match ($this->discount_type) {
            self::DISCOUNT_PERCENTAGE => "{$this->discount_value}% off",
            self::DISCOUNT_FIXED_AMOUNT => '$' . number_format($this->discount_value, 2) . ' off',
            default => 'Discount',
        };
    }

    /**
     * Get the savings amount in currency.
     */
    public function getSavingsAmount(): float
    {
        return match ($this->offer_type) {
            self::TYPE_DISCOUNT => $this->getDiscountAmount(),
            self::TYPE_FREE_MONTHS => $this->subscription->plan->price * $this->free_months,
            self::TYPE_PLAN_DOWNGRADE => max(0, $this->subscription->plan->price - $this->downgradePlan->price),
            default => 0.0,
        };
    }

    /**
     * Get formatted savings display.
     */
    public function getFormattedSavingsAttribute(): string
    {
        $savings = $this->getSavingsAmount();
        
        if ($savings <= 0) {
            return 'No savings';
        }
        
        return '$' . number_format($savings, 2) . ' savings';
    }

    /**
     * Get offer urgency level based on time remaining.
     */
    public function getUrgencyLevel(): string
    {
        if (!$this->isValid()) {
            return 'expired';
        }
        
        $hoursRemaining = now()->diffInHours($this->valid_until);
        
        return match (true) {
            $hoursRemaining <= 24 => 'high',
            $hoursRemaining <= 72 => 'medium',
            default => 'low',
        };
    }

    /**
     * Get time remaining until expiry.
     */
    public function getTimeRemainingAttribute(): string
    {
        if (!$this->isValid()) {
            return 'Expired';
        }
        
        $now = now();
        $validUntil = $this->valid_until;
        
        if ($validUntil->diffInHours($now) < 24) {
            return $validUntil->diffInHours($now) . ' hours remaining';
        }
        
        return $validUntil->diffInDays($now) . ' days remaining';
    }

    /**
     * Scope to get valid offers.
     */
    public function scopeValid($query)
    {
        return $query->where('is_accepted', false)
                    ->where('valid_until', '>', now());
    }

    /**
     * Scope to get expired offers.
     */
    public function scopeExpired($query)
    {
        return $query->where('valid_until', '<=', now());
    }

    /**
     * Scope to get accepted offers.
     */
    public function scopeAccepted($query)
    {
        return $query->where('is_accepted', true);
    }

    /**
     * Create a percentage discount offer.
     */
    public static function createPercentageDiscount(
        Subscription $subscription, 
        float $percentage, 
        int $validHours = 72,
        string $description = null
    ): self {
        return self::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'offer_type' => self::TYPE_DISCOUNT,
            'discount_type' => self::DISCOUNT_PERCENTAGE,
            'discount_value' => $percentage,
            'offer_description' => $description ?? "Get {$percentage}% off your subscription",
            'valid_until' => now()->addHours($validHours),
            'terms' => [
                'applies_to_next_billing_cycle' => true,
                'one_time_offer' => true,
            ],
        ]);
    }

    /**
     * Create a fixed amount discount offer.
     */
    public static function createFixedDiscount(
        Subscription $subscription, 
        float $amount, 
        int $validHours = 72,
        string $description = null
    ): self {
        return self::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'offer_type' => self::TYPE_DISCOUNT,
            'discount_type' => self::DISCOUNT_FIXED_AMOUNT,
            'discount_value' => $amount,
            'offer_description' => $description ?? "Get \${$amount} off your subscription",
            'valid_until' => now()->addHours($validHours),
            'terms' => [
                'applies_to_next_billing_cycle' => true,
                'one_time_offer' => true,
            ],
        ]);
    }

    /**
     * Create a free months offer.
     */
    public static function createFreeMonths(
        Subscription $subscription, 
        int $months, 
        int $validHours = 72,
        string $description = null
    ): self {
        return self::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'offer_type' => self::TYPE_FREE_MONTHS,
            'free_months' => $months,
            'offer_description' => $description ?? "Get {$months} free months",
            'valid_until' => now()->addHours($validHours),
            'terms' => [
                'extends_current_period' => true,
                'one_time_offer' => true,
            ],
        ]);
    }

    /**
     * Create a plan downgrade offer.
     */
    public static function createPlanDowngrade(
        Subscription $subscription, 
        Plan $downgradePlan, 
        int $validHours = 72,
        string $description = null
    ): self {
        return self::create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'offer_type' => self::TYPE_PLAN_DOWNGRADE,
            'downgrade_plan_id' => $downgradePlan->id,
            'offer_description' => $description ?? "Switch to {$downgradePlan->name} plan",
            'valid_until' => now()->addHours($validHours),
            'terms' => [
                'immediate_downgrade' => true,
                'prorated_refund' => true,
            ],
        ]);
    }
}