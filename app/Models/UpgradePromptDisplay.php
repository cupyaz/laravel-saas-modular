<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UpgradePromptDisplay extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'upgrade_prompt_id',
        'variant',
        'context',
        'placement_location',
        'action_taken',
        'dismissed_at',
        'clicked_at',
        'converted_at',
    ];

    protected $casts = [
        'context' => 'array',
        'dismissed_at' => 'datetime',
        'clicked_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    /**
     * Get the tenant that was shown this prompt.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the prompt that was displayed.
     */
    public function upgradePrompt(): BelongsTo
    {
        return $this->belongsTo(UpgradePrompt::class);
    }

    /**
     * Get the conversion record if this display led to an upgrade.
     */
    public function conversion(): HasOne
    {
        return $this->hasOne(UpgradeConversion::class);
    }

    /**
     * Scope for displays with specific actions.
     */
    public function scopeWithAction($query, string $action)
    {
        return $query->where('action_taken', $action);
    }

    /**
     * Scope for dismissed displays.
     */
    public function scopeDismissed($query)
    {
        return $query->where('action_taken', 'dismissed');
    }

    /**
     * Scope for clicked displays.
     */
    public function scopeClicked($query)
    {
        return $query->where('action_taken', 'clicked');
    }

    /**
     * Scope for converted displays.
     */
    public function scopeConverted($query)
    {
        return $query->where('action_taken', 'converted');
    }

    /**
     * Scope for recent displays.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Mark the display as dismissed.
     */
    public function markAsDismissed(): bool
    {
        return $this->update([
            'action_taken' => 'dismissed',
            'dismissed_at' => now(),
        ]);
    }

    /**
     * Mark the display as clicked.
     */
    public function markAsClicked(): bool
    {
        return $this->update([
            'action_taken' => 'clicked',
            'clicked_at' => now(),
        ]);
    }

    /**
     * Mark the display as converted.
     */
    public function markAsConverted(): bool
    {
        return $this->update([
            'action_taken' => 'converted',
            'converted_at' => now(),
        ]);
    }

    /**
     * Check if this display was part of an A/B test.
     */
    public function isABTestVariant(): bool
    {
        return !is_null($this->variant) && $this->variant !== 'control';
    }

    /**
     * Get the time since this prompt was displayed.
     */
    public function getTimeSinceDisplay(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if this display resulted in a conversion.
     */
    public function hasConverted(): bool
    {
        return $this->action_taken === 'converted' || $this->conversion()->exists();
    }

    /**
     * Get engagement score (0-100) based on actions taken.
     */
    public function getEngagementScore(): int
    {
        return match ($this->action_taken) {
            'converted' => 100,
            'clicked' => 70,
            'dismissed' => 30,
            default => 0,
        };
    }
}