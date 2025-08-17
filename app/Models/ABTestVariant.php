<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ABTestVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_name',
        'variant_name',
        'configuration',
        'traffic_percentage',
        'is_active',
        'start_date',
        'end_date',
        'success_metrics',
    ];

    protected $casts = [
        'configuration' => 'array',
        'success_metrics' => 'array',
        'is_active' => 'boolean',
        'traffic_percentage' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the assignments for this variant.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ABTestAssignment::class, 'variant_name', 'variant_name')
            ->where('test_name', $this->test_name);
    }

    /**
     * Scope for active variants.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope by test name.
     */
    public function scopeForTest($query, string $testName)
    {
        return $query->where('test_name', $testName);
    }

    /**
     * Check if this variant is currently running.
     */
    public function isRunning(): bool
    {
        return $this->is_active &&
               $this->start_date <= now() &&
               ($this->end_date === null || $this->end_date >= now());
    }

    /**
     * Get the number of users assigned to this variant.
     */
    public function getAssignmentCount(): int
    {
        return $this->assignments()->count();
    }

    /**
     * Get conversion rate for this variant.
     */
    public function getConversionRate(): float
    {
        $totalAssignments = $this->getAssignmentCount();
        if ($totalAssignments === 0) {
            return 0.0;
        }

        // Count conversions from prompts shown to users in this variant
        $conversions = UpgradePromptDisplay::whereIn('tenant_id', 
            $this->assignments()->pluck('tenant_id')
        )
        ->where('variant', $this->variant_name)
        ->where('action_taken', 'converted')
        ->count();

        return ($conversions / $totalAssignments) * 100;
    }

    /**
     * Get click-through rate for this variant.
     */
    public function getClickThroughRate(): float
    {
        $totalDisplays = UpgradePromptDisplay::whereIn('tenant_id', 
            $this->assignments()->pluck('tenant_id')
        )
        ->where('variant', $this->variant_name)
        ->count();

        if ($totalDisplays === 0) {
            return 0.0;
        }

        $clicks = UpgradePromptDisplay::whereIn('tenant_id', 
            $this->assignments()->pluck('tenant_id')
        )
        ->where('variant', $this->variant_name)
        ->where('action_taken', 'clicked')
        ->count();

        return ($clicks / $totalDisplays) * 100;
    }

    /**
     * Get statistical significance compared to control.
     */
    public function getStatisticalSignificance(): ?array
    {
        if ($this->variant_name === 'control') {
            return null;
        }

        $control = static::forTest($this->test_name)
            ->where('variant_name', 'control')
            ->first();

        if (!$control) {
            return null;
        }

        $controlRate = $control->getConversionRate();
        $variantRate = $this->getConversionRate();
        
        $controlCount = $control->getAssignmentCount();
        $variantCount = $this->getAssignmentCount();

        // Basic statistical significance calculation
        if ($controlCount < 30 || $variantCount < 30) {
            return ['significant' => false, 'reason' => 'Insufficient sample size'];
        }

        $pooledRate = ($controlRate * $controlCount + $variantRate * $variantCount) / ($controlCount + $variantCount);
        $standardError = sqrt($pooledRate * (1 - $pooledRate) * (1/$controlCount + 1/$variantCount));
        
        if ($standardError == 0) {
            return ['significant' => false, 'reason' => 'No variance'];
        }

        $zScore = abs($variantRate - $controlRate) / $standardError;
        $pValue = 2 * (1 - $this->normalCDF($zScore));

        return [
            'significant' => $pValue < 0.05,
            'p_value' => $pValue,
            'z_score' => $zScore,
            'confidence_level' => (1 - $pValue) * 100,
            'improvement' => $variantRate - $controlRate,
        ];
    }

    /**
     * Normal cumulative distribution function approximation.
     */
    private function normalCDF($x): float
    {
        return 0.5 * (1 + erf($x / sqrt(2)));
    }
}