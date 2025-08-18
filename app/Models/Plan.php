<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_period',
        'features',
        'limits',
        'is_active',
        'stripe_price_id',
        'trial_days',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'trial_days' => 'integer',
        ];
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Check if the plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get the limit for a specific resource.
     */
    public function getLimit(string $resource): ?int
    {
        return $this->limits[$resource] ?? null;
    }

    /**
     * Check if the plan is free.
     */
    public function isFree(): bool
    {
        return $this->price == 0;
    }

    /**
     * Get the formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        return '$' . number_format($this->price, 2);
    }

    /**
     * Get the billing period display name.
     */
    public function getBillingPeriodDisplayAttribute(): string
    {
        return match ($this->billing_period) {
            'monthly' => 'per month',
            'yearly' => 'per year',
            default => $this->billing_period,
        };
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get Stripe price ID based on billing period.
     */
    public function getStripePriceId(): ?string
    {
        return $this->stripe_price_id;
    }

    /**
     * Check if the plan allows a specific number of items for a resource.
     */
    public function allowsQuantity(string $resource, int $quantity): bool
    {
        $limit = $this->getLimit($resource);
        
        if ($limit === null) {
            return true; // Unlimited
        }
        
        return $quantity <= $limit;
    }

    /**
     * Get the features included in this plan.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('limit', 'is_included')
            ->withTimestamps()
            ->using(PlanFeature::class);
    }

    /**
     * Check if the plan includes a specific feature.
     */
    public function includesFeature(string $featureSlug): bool
    {
        return $this->features()
            ->where('slug', $featureSlug)
            ->wherePivot('is_included', true)
            ->exists();
    }

    /**
     * Get the limit for a specific feature.
     */
    public function getFeatureLimit(string $featureSlug): ?int
    {
        $feature = $this->features()
            ->where('slug', $featureSlug)
            ->wherePivot('is_included', true)
            ->first();

        if (!$feature) {
            return 0; // Feature not included
        }

        return $feature->pivot->limit;
    }

    /**
     * Check if a feature allows a specific quantity.
     */
    public function allowsFeatureQuantity(string $featureSlug, int $quantity): bool
    {
        $feature = $this->features()
            ->where('slug', $featureSlug)
            ->wherePivot('is_included', true)
            ->first();

        if (!$feature) {
            return false; // Feature not included
        }

        return $feature->pivot->allowsQuantity($quantity);
    }

    /**
     * Get all free tier features with their limits.
     */
    public function getFreeTierFeatures(): array
    {
        if (!$this->isFree()) {
            return [];
        }

        return $this->features()
            ->wherePivot('is_included', true)
            ->get()
            ->map(function ($feature) {
                return [
                    'name' => $feature->name,
                    'slug' => $feature->slug,
                    'category' => $feature->category,
                    'limit' => $feature->pivot->limit,
                    'is_unlimited' => $feature->pivot->isUnlimited(),
                ];
            })
            ->toArray();
    }

    /**
     * Scope to get free plans.
     */
    public function scopeFree($query)
    {
        return $query->where('price', 0);
    }

    /**
     * Scope to get paid plans.
     */
    public function scopePaid($query)
    {
        return $query->where('price', '>', 0);
    }
}