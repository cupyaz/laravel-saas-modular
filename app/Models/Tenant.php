<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use HasFactory, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'domain',
        'database',
        'config',
        'is_active',
        'trial_ends_at',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'billing_address',
        'tax_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'billing_address' => 'array',
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant's primary user (owner).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the users that belong to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the tenant's subscriptions.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the tenant's active subscription.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Get the tenant's owners.
     */
    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    /**
     * Get the tenant's members.
     */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'member');
    }

    /**
     * Check if tenant is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant trial has expired.
     */
    public function trialExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Get tenant configuration value.
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set tenant configuration value.
     */
    public function setConfig(string $key, $value): void
    {
        $config = $this->config ?? [];
        $config[$key] = $value;
        $this->config = $config;
    }

    /**
     * Check if tenant has active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription()?->isActive() ?? false;
    }

    /**
     * Check if tenant can access feature.
     */
    public function canAccessFeature(string $feature): bool
    {
        $subscription = $this->subscription();
        
        if (!$subscription) {
            return false;
        }

        return $subscription->plan->hasFeature($feature);
    }

    /**
     * Get tenant's current plan.
     */
    public function currentPlan(): ?Plan
    {
        return $this->subscription()?->plan;
    }
}