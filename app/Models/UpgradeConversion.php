<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpgradeConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'upgrade_prompt_display_id',
        'subscription_id',
        'from_plan_id',
        'to_plan_id',
        'conversion_value',
        'conversion_data',
    ];

    protected $casts = [
        'conversion_value' => 'decimal:2',
        'conversion_data' => 'array',
    ];

    /**
     * Get the tenant that converted.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the prompt display that led to this conversion.
     */
    public function promptDisplay(): BelongsTo
    {
        return $this->belongsTo(UpgradePromptDisplay::class, 'upgrade_prompt_display_id');
    }

    /**
     * Get the subscription that was created/updated.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the plan the user converted from.
     */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    /**
     * Get the plan the user converted to.
     */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    /**
     * Scope for high-value conversions.
     */
    public function scopeHighValue($query, float $threshold = 50.00)
    {
        return $query->where('conversion_value', '>=', $threshold);
    }

    /**
     * Scope for recent conversions.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for conversions to specific plan.
     */
    public function scopeToPlan($query, int $planId)
    {
        return $query->where('to_plan_id', $planId);
    }

    /**
     * Scope for conversions from specific plan.
     */
    public function scopeFromPlan($query, int $planId)
    {
        return $query->where('from_plan_id', $planId);
    }

    /**
     * Get the conversion value formatted as currency.
     */
    public function getFormattedValueAttribute(): string
    {
        return '$' . number_format($this->conversion_value, 2);
    }

    /**
     * Get the upgrade type (trial_to_paid, free_to_paid, plan_upgrade).
     */
    public function getUpgradeTypeAttribute(): string
    {
        if ($this->fromPlan->isFree() && !$this->toPlan->isFree()) {
            return 'free_to_paid';
        }

        if (isset($this->conversion_data['was_trial']) && $this->conversion_data['was_trial']) {
            return 'trial_to_paid';
        }

        return 'plan_upgrade';
    }

    /**
     * Get the time from prompt display to conversion.
     */
    public function getTimeToConversion(): ?\DateInterval
    {
        if (!$this->promptDisplay) {
            return null;
        }

        return $this->promptDisplay->created_at->diff($this->created_at);
    }

    /**
     * Calculate the revenue increase from this conversion.
     */
    public function getRevenueIncrease(): float
    {
        return $this->toPlan->price - $this->fromPlan->price;
    }
}