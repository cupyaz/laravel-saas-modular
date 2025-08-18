<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
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
        'category',
        'is_premium',
        'default_limit',
        'is_active',
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
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'default_limit' => 'integer',
        ];
    }

    /**
     * Feature categories
     */
    const CATEGORY_CORE = 'core';
    const CATEGORY_ANALYTICS = 'analytics';
    const CATEGORY_COLLABORATION = 'collaboration';
    const CATEGORY_INTEGRATION = 'integration';
    const CATEGORY_STORAGE = 'storage';
    const CATEGORY_SUPPORT = 'support';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_API = 'api';

    /**
     * Get the plans that include this feature.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot('limit', 'is_included')
            ->withTimestamps();
    }

    /**
     * Check if this feature requires a premium plan.
     */
    public function isPremium(): bool
    {
        return $this->is_premium;
    }

    /**
     * Check if this feature is available for free tier.
     */
    public function isFreeTier(): bool
    {
        return !$this->is_premium;
    }

    /**
     * Get the default limit for this feature.
     */
    public function getDefaultLimit(): ?int
    {
        return $this->default_limit;
    }

    /**
     * Check if feature has unlimited usage by default.
     */
    public function isUnlimited(): bool
    {
        return $this->default_limit === null || $this->default_limit === -1;
    }

    /**
     * Get feature metadata value.
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set feature metadata value.
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Scope to get only active features.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get free tier features.
     */
    public function scopeFreeTier($query)
    {
        return $query->where('is_premium', false);
    }

    /**
     * Scope to get premium features.
     */
    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    /**
     * Scope to get features by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_CORE => 'Core Features',
            self::CATEGORY_ANALYTICS => 'Analytics & Reporting',
            self::CATEGORY_COLLABORATION => 'Team Collaboration',
            self::CATEGORY_INTEGRATION => 'Integrations',
            self::CATEGORY_STORAGE => 'Storage & Files',
            self::CATEGORY_SUPPORT => 'Customer Support',
            self::CATEGORY_SECURITY => 'Security & Compliance',
            self::CATEGORY_API => 'API Access',
        ];
    }

    /**
     * Get category display name.
     */
    public function getCategoryDisplayAttribute(): string
    {
        return self::getCategories()[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Check if feature is in specific category.
     */
    public function isInCategory(string $category): bool
    {
        return $this->category === $category;
    }
}