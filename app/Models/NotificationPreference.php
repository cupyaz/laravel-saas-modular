<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_id',
        'enabled_channels',
        'is_enabled',
        'conditions',
        'frequency',
        'preferred_time',
    ];

    protected $casts = [
        'enabled_channels' => 'array',
        'is_enabled' => 'boolean',
        'conditions' => 'array',
        'preferred_time' => 'datetime',
    ];

    // Frequency constants
    public const FREQUENCY_IMMEDIATE = 'immediate';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_NEVER = 'never';

    /**
     * Get the user this preference belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the notification template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * Scope for enabled preferences.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope by frequency.
     */
    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope for immediate notifications.
     */
    public function scopeImmediate($query)
    {
        return $query->where('frequency', self::FREQUENCY_IMMEDIATE);
    }

    /**
     * Check if a specific channel is enabled.
     */
    public function isChannelEnabled(string $channel): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        return in_array($channel, $this->enabled_channels ?? []);
    }

    /**
     * Enable a channel.
     */
    public function enableChannel(string $channel): self
    {
        $channels = $this->enabled_channels ?? [];
        if (!in_array($channel, $channels)) {
            $channels[] = $channel;
            $this->update(['enabled_channels' => $channels]);
        }

        return $this;
    }

    /**
     * Disable a channel.
     */
    public function disableChannel(string $channel): self
    {
        $channels = $this->enabled_channels ?? [];
        $channels = array_values(array_filter($channels, fn($c) => $c !== $channel));
        $this->update(['enabled_channels' => $channels]);

        return $this;
    }

    /**
     * Toggle channel status.
     */
    public function toggleChannel(string $channel): self
    {
        if ($this->isChannelEnabled($channel)) {
            $this->disableChannel($channel);
        } else {
            $this->enableChannel($channel);
        }

        return $this;
    }

    /**
     * Set frequency.
     */
    public function setFrequency(string $frequency): self
    {
        $this->update(['frequency' => $frequency]);
        return $this;
    }

    /**
     * Enable preference.
     */
    public function enable(): self
    {
        $this->update(['is_enabled' => true]);
        return $this;
    }

    /**
     * Disable preference.
     */
    public function disable(): self
    {
        $this->update(['is_enabled' => false]);
        return $this;
    }

    /**
     * Toggle enabled status.
     */
    public function toggle(): self
    {
        $this->update(['is_enabled' => !$this->is_enabled]);
        return $this;
    }

    /**
     * Get all available frequencies.
     */
    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_IMMEDIATE => 'Immediate',
            self::FREQUENCY_DAILY => 'Daily Digest',
            self::FREQUENCY_WEEKLY => 'Weekly Summary',
            self::FREQUENCY_NEVER => 'Never',
        ];
    }

    /**
     * Create or update preference for user and template.
     */
    public static function setForUser(
        int $userId,
        int $templateId,
        array $enabledChannels,
        bool $isEnabled = true,
        string $frequency = self::FREQUENCY_IMMEDIATE
    ): self {
        return self::updateOrCreate(
            [
                'user_id' => $userId,
                'template_id' => $templateId,
            ],
            [
                'enabled_channels' => $enabledChannels,
                'is_enabled' => $isEnabled,
                'frequency' => $frequency,
            ]
        );
    }

    /**
     * Bulk update preferences for a user.
     */
    public static function bulkUpdateForUser(int $userId, array $preferences): void
    {
        foreach ($preferences as $templateId => $settings) {
            self::setForUser(
                $userId,
                $templateId,
                $settings['enabled_channels'] ?? [],
                $settings['is_enabled'] ?? true,
                $settings['frequency'] ?? self::FREQUENCY_IMMEDIATE
            );
        }
    }

    /**
     * Get default preferences for a user based on template defaults.
     */
    public static function createDefaultsForUser(int $userId): void
    {
        $templates = NotificationTemplate::active()->get();

        foreach ($templates as $template) {
            // Only create if preference doesn't exist
            if (!self::where('user_id', $userId)->where('template_id', $template->id)->exists()) {
                self::create([
                    'user_id' => $userId,
                    'template_id' => $template->id,
                    'enabled_channels' => $template->default_channels ?? [],
                    'is_enabled' => true,
                    'frequency' => self::FREQUENCY_IMMEDIATE,
                ]);
            }
        }
    }

    /**
     * Reset preferences to template defaults.
     */
    public function resetToDefaults(): self
    {
        $template = $this->template;
        
        $this->update([
            'enabled_channels' => $template->default_channels ?? [],
            'is_enabled' => true,
            'frequency' => self::FREQUENCY_IMMEDIATE,
            'conditions' => null,
        ]);

        return $this;
    }

    /**
     * Get enabled preferences summary for user.
     */
    public static function getSummaryForUser(int $userId): array
    {
        $preferences = self::where('user_id', $userId)
            ->with('template')
            ->get()
            ->groupBy('template.category');

        $summary = [];
        foreach ($preferences as $category => $categoryPreferences) {
            $summary[$category] = [
                'total' => $categoryPreferences->count(),
                'enabled' => $categoryPreferences->where('is_enabled', true)->count(),
                'channels_usage' => $categoryPreferences
                    ->where('is_enabled', true)
                    ->flatMap(fn($p) => $p->enabled_channels ?? [])
                    ->countBy()
                    ->toArray(),
            ];
        }

        return $summary;
    }
}