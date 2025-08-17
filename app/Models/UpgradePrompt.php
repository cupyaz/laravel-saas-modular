<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UpgradePrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'trigger_condition',
        'content',
        'targeting_rules',
        'placement',
        'priority',
        'is_active',
        'max_displays_per_user',
        'cooldown_hours',
        'ab_test_config',
    ];

    protected $casts = [
        'trigger_condition' => 'array',
        'content' => 'array',
        'targeting_rules' => 'array',
        'ab_test_config' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'max_displays_per_user' => 'integer',
        'cooldown_hours' => 'integer',
    ];

    /**
     * Get the displays for this prompt.
     */
    public function displays(): HasMany
    {
        return $this->hasMany(UpgradePromptDisplay::class);
    }

    /**
     * Scope for active prompts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by prompt type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by placement.
     */
    public function scopeForPlacement($query, string $placement)
    {
        return $query->where('placement', $placement);
    }

    /**
     * Order by priority.
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if this prompt should be shown to a tenant.
     */
    public function shouldShowToTenant(Tenant $tenant, array $context = []): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if tenant has reached max displays
        $displayCount = $this->displays()
            ->where('tenant_id', $tenant->id)
            ->count();

        if ($displayCount >= $this->max_displays_per_user) {
            return false;
        }

        // Check cooldown period
        $lastDisplay = $this->displays()
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->first();

        if ($lastDisplay && $lastDisplay->created_at->addHours($this->cooldown_hours)->isFuture()) {
            return false;
        }

        // Check targeting rules
        if (!$this->matchesTargetingRules($tenant, $context)) {
            return false;
        }

        // Check trigger conditions
        return $this->matchesTriggerConditions($tenant, $context);
    }

    /**
     * Check if tenant matches targeting rules.
     */
    protected function matchesTargetingRules(Tenant $tenant, array $context = []): bool
    {
        $rules = $this->targeting_rules ?? [];

        foreach ($rules as $rule => $value) {
            switch ($rule) {
                case 'plan_slugs':
                    $currentPlan = $tenant->currentPlan();
                    if ($currentPlan && !in_array($currentPlan->slug, $value)) {
                        return false;
                    }
                    break;

                case 'subscription_status':
                    $subscription = $tenant->subscription();
                    if ($subscription && !in_array($subscription->status, $value)) {
                        return false;
                    }
                    break;

                case 'trial_days_remaining':
                    $subscription = $tenant->subscription();
                    if ($subscription && $subscription->onTrial()) {
                        $remaining = $subscription->getRemainingTrialDays();
                        if ($remaining < $value['min'] || $remaining > $value['max']) {
                            return false;
                        }
                    }
                    break;

                case 'usage_percentage':
                    $feature = $value['feature'];
                    $metric = $value['metric'];
                    $threshold = $value['threshold'];
                    
                    $usageTracker = app(\App\Services\UsageTracker::class);
                    $summary = $usageTracker->getUsageSummary($tenant->id);
                    $key = "{$feature}.{$metric}";
                    
                    if (!isset($summary[$key]) || $summary[$key]['percentage_used'] < $threshold) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Check if trigger conditions are met.
     */
    protected function matchesTriggerConditions(Tenant $tenant, array $context = []): bool
    {
        $conditions = $this->trigger_condition ?? [];

        foreach ($conditions as $condition => $value) {
            switch ($condition) {
                case 'usage_limit_reached':
                    $feature = $value['feature'];
                    $metric = $value['metric'];
                    $percentage = $value['percentage'] ?? 80;
                    
                    $usageTracker = app(\App\Services\UsageTracker::class);
                    $summary = $usageTracker->getUsageSummary($tenant->id);
                    $key = "{$feature}.{$metric}";
                    
                    if (!isset($summary[$key]) || $summary[$key]['percentage_used'] < $percentage) {
                        return false;
                    }
                    break;

                case 'feature_access_denied':
                    $feature = $value['feature'];
                    if (!isset($context['denied_feature']) || $context['denied_feature'] !== $feature) {
                        return false;
                    }
                    break;

                case 'specific_action':
                    $action = $value['action'];
                    if (!isset($context['action']) || $context['action'] !== $action) {
                        return false;
                    }
                    break;

                case 'time_since_signup':
                    $days = $value['days'];
                    if ($tenant->created_at->diffInDays(now()) < $days) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Get the content for a specific variant.
     */
    public function getContentForVariant(string $variant = 'control'): array
    {
        $content = $this->content;

        // If A/B testing is configured, merge variant-specific content
        if ($this->ab_test_config && isset($this->ab_test_config['variants'][$variant])) {
            $variantContent = $this->ab_test_config['variants'][$variant];
            $content = array_merge($content, $variantContent);
        }

        return $content;
    }

    /**
     * Get conversion rate for this prompt.
     */
    public function getConversionRate(): float
    {
        $totalDisplays = $this->displays()->count();
        if ($totalDisplays === 0) {
            return 0.0;
        }

        $conversions = $this->displays()
            ->where('action_taken', 'converted')
            ->count();

        return ($conversions / $totalDisplays) * 100;
    }

    /**
     * Get click-through rate for this prompt.
     */
    public function getClickThroughRate(): float
    {
        $totalDisplays = $this->displays()->count();
        if ($totalDisplays === 0) {
            return 0.0;
        }

        $clicks = $this->displays()
            ->where('action_taken', 'clicked')
            ->count();

        return ($clicks / $totalDisplays) * 100;
    }
}