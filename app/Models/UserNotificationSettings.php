<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserNotificationSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notifications_enabled',
        'global_channels',
        'marketing_enabled',
        'product_updates_enabled',
        'security_alerts_enabled',
        'digest_frequency',
        'digest_time',
        'timezone',
        'do_not_disturb_enabled',
        'dnd_start_time',
        'dnd_end_time',
        'dnd_days',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'global_channels' => 'array',
        'marketing_enabled' => 'boolean',
        'product_updates_enabled' => 'boolean',
        'security_alerts_enabled' => 'boolean',
        'digest_time' => 'datetime',
        'do_not_disturb_enabled' => 'boolean',
        'dnd_start_time' => 'datetime',
        'dnd_end_time' => 'datetime',
        'dnd_days' => 'array',
    ];

    // Digest frequency constants
    public const DIGEST_NONE = 'none';
    public const DIGEST_DAILY = 'daily';
    public const DIGEST_WEEKLY = 'weekly';
    public const DIGEST_MONTHLY = 'monthly';

    /**
     * Get the user these settings belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notifications are enabled globally.
     */
    public function areNotificationsEnabled(): bool
    {
        return $this->notifications_enabled;
    }

    /**
     * Check if a specific channel is disabled globally.
     */
    public function isChannelDisabledGlobally(string $channel): bool
    {
        return in_array($channel, $this->global_channels ?? []);
    }

    /**
     * Check if marketing notifications are enabled.
     */
    public function isMarketingEnabled(): bool
    {
        return $this->marketing_enabled && $this->notifications_enabled;
    }

    /**
     * Check if product update notifications are enabled.
     */
    public function areProductUpdatesEnabled(): bool
    {
        return $this->product_updates_enabled && $this->notifications_enabled;
    }

    /**
     * Check if security alerts are enabled.
     */
    public function areSecurityAlertsEnabled(): bool
    {
        return $this->security_alerts_enabled && $this->notifications_enabled;
    }

    /**
     * Check if user is in do not disturb mode at current time.
     */
    public function isInDoNotDisturbMode(?Carbon $time = null): bool
    {
        if (!$this->do_not_disturb_enabled) {
            return false;
        }

        $time = $time ?? now()->setTimezone($this->timezone);
        $currentDay = strtolower($time->format('l')); // monday, tuesday, etc.
        $currentTime = $time->format('H:i:s');

        // Check if current day is in DND days
        $dndDays = $this->dnd_days ?? [];
        if (!empty($dndDays) && !in_array($currentDay, $dndDays)) {
            return false;
        }

        // Check time range
        if (!$this->dnd_start_time || !$this->dnd_end_time) {
            return false;
        }

        $startTime = $this->dnd_start_time->format('H:i:s');
        $endTime = $this->dnd_end_time->format('H:i:s');

        // Handle overnight DND (e.g., 22:00 to 08:00)
        if ($startTime > $endTime) {
            return $currentTime >= $startTime || $currentTime <= $endTime;
        }

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Check if notification should be sent based on these settings.
     */
    public function shouldReceiveNotification(string $templateName, string $channel, string $category = null): bool
    {
        // Global notifications disabled
        if (!$this->notifications_enabled) {
            return false;
        }

        // Channel disabled globally
        if ($this->isChannelDisabledGlobally($channel)) {
            return false;
        }

        // Category-specific checks
        if ($category) {
            switch ($category) {
                case NotificationTemplate::CATEGORY_MARKETING:
                    if (!$this->marketing_enabled) {
                        return false;
                    }
                    break;
                case NotificationTemplate::CATEGORY_SYSTEM:
                    if (!$this->product_updates_enabled) {
                        return false;
                    }
                    break;
                case NotificationTemplate::CATEGORY_SECURITY:
                    if (!$this->security_alerts_enabled) {
                        return false;
                    }
                    break;
            }
        }

        // Do not disturb check (except for critical security alerts)
        if ($this->isInDoNotDisturbMode() && $category !== NotificationTemplate::CATEGORY_SECURITY) {
            return false;
        }

        return true;
    }

    /**
     * Get digest frequency.
     */
    public function getDigestFrequency(): string
    {
        return $this->digest_frequency ?? self::DIGEST_DAILY;
    }

    /**
     * Check if user wants digest notifications.
     */
    public function wantsDigest(): bool
    {
        return $this->getDigestFrequency() !== self::DIGEST_NONE;
    }

    /**
     * Get next digest time.
     */
    public function getNextDigestTime(): ?Carbon
    {
        if (!$this->wantsDigest()) {
            return null;
        }

        $now = now()->setTimezone($this->timezone);
        $digestTime = $this->digest_time ? 
            $now->copy()->setTimeFrom($this->digest_time) : 
            $now->copy()->setTime(9, 0, 0); // Default 9 AM

        switch ($this->getDigestFrequency()) {
            case self::DIGEST_DAILY:
                if ($digestTime->isPast()) {
                    $digestTime->addDay();
                }
                return $digestTime;

            case self::DIGEST_WEEKLY:
                $digestTime->next(Carbon::MONDAY);
                if ($digestTime->isPast()) {
                    $digestTime->addWeek();
                }
                return $digestTime;

            case self::DIGEST_MONTHLY:
                $digestTime->startOfMonth()->addDay()->setTimeFrom($this->digest_time ?? $digestTime);
                if ($digestTime->isPast()) {
                    $digestTime->addMonth();
                }
                return $digestTime;
        }

        return null;
    }

    /**
     * Enable all notifications.
     */
    public function enableAllNotifications(): self
    {
        $this->update([
            'notifications_enabled' => true,
            'marketing_enabled' => true,
            'product_updates_enabled' => true,
            'security_alerts_enabled' => true,
        ]);

        return $this;
    }

    /**
     * Disable all notifications except security.
     */
    public function disableAllNotifications(): self
    {
        $this->update([
            'notifications_enabled' => false,
            'marketing_enabled' => false,
            'product_updates_enabled' => false,
            // Keep security alerts enabled for safety
            'security_alerts_enabled' => true,
        ]);

        return $this;
    }

    /**
     * Disable channel globally.
     */
    public function disableChannelGlobally(string $channel): self
    {
        $channels = $this->global_channels ?? [];
        if (!in_array($channel, $channels)) {
            $channels[] = $channel;
            $this->update(['global_channels' => $channels]);
        }

        return $this;
    }

    /**
     * Enable channel globally.
     */
    public function enableChannelGlobally(string $channel): self
    {
        $channels = $this->global_channels ?? [];
        $channels = array_values(array_filter($channels, fn($c) => $c !== $channel));
        $this->update(['global_channels' => $channels]);

        return $this;
    }

    /**
     * Set do not disturb schedule.
     */
    public function setDoNotDisturbSchedule(
        string $startTime,
        string $endTime,
        array $days = [],
        bool $enabled = true
    ): self {
        $this->update([
            'do_not_disturb_enabled' => $enabled,
            'dnd_start_time' => Carbon::createFromFormat('H:i', $startTime),
            'dnd_end_time' => Carbon::createFromFormat('H:i', $endTime),
            'dnd_days' => $days,
        ]);

        return $this;
    }

    /**
     * Set digest preferences.
     */
    public function setDigestPreferences(string $frequency, string $time = '09:00'): self
    {
        $this->update([
            'digest_frequency' => $frequency,
            'digest_time' => Carbon::createFromFormat('H:i', $time),
        ]);

        return $this;
    }

    /**
     * Get all available digest frequencies.
     */
    public static function getDigestFrequencies(): array
    {
        return [
            self::DIGEST_NONE => 'No Digest',
            self::DIGEST_DAILY => 'Daily',
            self::DIGEST_WEEKLY => 'Weekly',
            self::DIGEST_MONTHLY => 'Monthly',
        ];
    }

    /**
     * Create default settings for user.
     */
    public static function createDefaultsForUser(int $userId): self
    {
        return self::create([
            'user_id' => $userId,
            'notifications_enabled' => true,
            'global_channels' => [],
            'marketing_enabled' => true,
            'product_updates_enabled' => true,
            'security_alerts_enabled' => true,
            'digest_frequency' => self::DIGEST_DAILY,
            'digest_time' => Carbon::createFromTime(9, 0, 0),
            'timezone' => 'UTC',
            'do_not_disturb_enabled' => false,
        ]);
    }

    /**
     * Get or create settings for user.
     */
    public static function getForUser(int $userId): self
    {
        return self::where('user_id', $userId)->first() ?? 
               self::createDefaultsForUser($userId);
    }

    /**
     * Get settings summary.
     */
    public function getSummary(): array
    {
        return [
            'notifications_enabled' => $this->notifications_enabled,
            'enabled_categories' => [
                'marketing' => $this->marketing_enabled,
                'product_updates' => $this->product_updates_enabled,
                'security_alerts' => $this->security_alerts_enabled,
            ],
            'disabled_channels' => $this->global_channels ?? [],
            'digest' => [
                'frequency' => $this->digest_frequency,
                'time' => $this->digest_time?->format('H:i'),
                'next_digest' => $this->getNextDigestTime()?->toISOString(),
            ],
            'do_not_disturb' => [
                'enabled' => $this->do_not_disturb_enabled,
                'active_now' => $this->isInDoNotDisturbMode(),
                'schedule' => [
                    'start' => $this->dnd_start_time?->format('H:i'),
                    'end' => $this->dnd_end_time?->format('H:i'),
                    'days' => $this->dnd_days ?? [],
                ],
            ],
            'timezone' => $this->timezone,
        ];
    }
}