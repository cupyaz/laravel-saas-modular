<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PlanFeature extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'plan_features';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plan_id',
        'feature_id',
        'limit',
        'is_included',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_included' => 'boolean',
            'limit' => 'integer',
        ];
    }

    /**
     * Check if the feature is unlimited for this plan.
     */
    public function isUnlimited(): bool
    {
        return $this->limit === null || $this->limit === -1;
    }

    /**
     * Get the effective limit for this feature.
     */
    public function getEffectiveLimit(): ?int
    {
        if (!$this->is_included) {
            return 0;
        }

        return $this->limit;
    }

    /**
     * Check if a quantity is within the allowed limit.
     */
    public function allowsQuantity(int $quantity): bool
    {
        if (!$this->is_included) {
            return false;
        }

        if ($this->isUnlimited()) {
            return true;
        }

        return $quantity <= $this->limit;
    }
}