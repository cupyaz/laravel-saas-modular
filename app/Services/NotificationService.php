<?php

namespace App\Services;

use App\Models\User;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\NotificationLog;
use App\Models\UserNotificationSettings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Send notification to user(s).
     */
    public function send(
        $users,
        string $templateName,
        array $variables = [],
        array $channels = null,
        array $options = []
    ): array {
        $users = is_array($users) ? $users : [$users];
        $results = [];

        $template = NotificationTemplate::where('name', $templateName)->first();
        if (!$template || !$template->is_active) {
            throw new \Exception("Notification template '{$templateName}' not found or inactive");
        }

        foreach ($users as $user) {
            if (is_int($user)) {
                $user = User::find($user);
            }

            if (!$user) {
                continue;
            }

            $results[$user->id] = $this->sendToUser($user, $template, $variables, $channels, $options);
        }

        return $results;
    }

    /**
     * Send notification to a single user.
     */
    protected function sendToUser(
        User $user,
        NotificationTemplate $template,
        array $variables = [],
        array $channels = null,
        array $options = []
    ): array {
        // Get user's notification settings
        $userSettings = UserNotificationSettings::getForUser($user->id);
        
        // Check if user should receive this notification
        if (!$userSettings->shouldReceiveNotification($template->name, 'any', $template->category)) {
            return [
                'sent' => false,
                'reason' => 'User has disabled this type of notification',
                'channels' => [],
            ];
        }

        // Get user preferences for this template
        $preference = NotificationPreference::where('user_id', $user->id)
            ->where('template_id', $template->id)
            ->first();

        if (!$preference) {
            // Create default preference
            $preference = NotificationPreference::create([
                'user_id' => $user->id,
                'template_id' => $template->id,
                'enabled_channels' => $template->default_channels ?? [],
                'is_enabled' => true,
                'frequency' => NotificationPreference::FREQUENCY_IMMEDIATE,
            ]);
        }

        if (!$preference->is_enabled) {
            return [
                'sent' => false,
                'reason' => 'User has disabled this specific notification',
                'channels' => [],
            ];
        }

        // Determine which channels to use
        $channelsToUse = $channels ?? $preference->enabled_channels ?? $template->default_channels ?? [];
        $channelsToUse = array_intersect($channelsToUse, $template->channels ?? []);

        // Filter out globally disabled channels
        $channelsToUse = array_filter($channelsToUse, function ($channel) use ($userSettings) {
            return !$userSettings->isChannelDisabledGlobally($channel);
        });

        if (empty($channelsToUse)) {
            return [
                'sent' => false,
                'reason' => 'No available channels for this user',
                'channels' => [],
            ];
        }

        // Check if should be queued based on frequency
        if ($preference->frequency !== NotificationPreference::FREQUENCY_IMMEDIATE) {
            return $this->queueForDigest($user, $template, $variables, $preference->frequency);
        }

        // Send immediate notification
        return $this->sendImmediate($user, $template, $variables, $channelsToUse, $options);
    }

    /**
     * Send immediate notification.
     */
    protected function sendImmediate(
        User $user,
        NotificationTemplate $template,
        array $variables,
        array $channels,
        array $options = []
    ): array {
        $results = [];
        
        foreach ($channels as $channel) {
            try {
                $result = $this->sendViaChannel($user, $template, $variables, $channel, $options);
                $results[$channel] = $result;
            } catch (\Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                Log::error("Failed to send notification via {$channel}", [
                    'user_id' => $user->id,
                    'template' => $template->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent' => true,
            'channels' => $results,
        ];
    }

    /**
     * Send notification via specific channel.
     */
    protected function sendViaChannel(
        User $user,
        NotificationTemplate $template,
        array $variables,
        string $channel,
        array $options = []
    ): array {
        // Create notification log entry
        $log = NotificationLog::createForNotification(
            null, // Will be set after notification is created
            $user->id,
            $template->name,
            $channel,
            $this->getRecipientForChannel($user, $channel),
            array_merge($variables, $options)
        );

        try {
            switch ($channel) {
                case NotificationTemplate::CHANNEL_DATABASE:
                    return $this->sendDatabaseNotification($user, $template, $variables, $log);
                
                case NotificationTemplate::CHANNEL_EMAIL:
                    return $this->sendEmailNotification($user, $template, $variables, $log);
                
                case NotificationTemplate::CHANNEL_SMS:
                    return $this->sendSmsNotification($user, $template, $variables, $log);
                
                case NotificationTemplate::CHANNEL_PUSH:
                    return $this->sendPushNotification($user, $template, $variables, $log);
                
                case NotificationTemplate::CHANNEL_SLACK:
                    return $this->sendSlackNotification($user, $template, $variables, $log);
                
                case NotificationTemplate::CHANNEL_WEBHOOK:
                    return $this->sendWebhookNotification($user, $template, $variables, $log);
                
                default:
                    throw new \Exception("Unsupported notification channel: {$channel}");
            }
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send database notification.
     */
    protected function sendDatabaseNotification(
        User $user,
        NotificationTemplate $template,
        array $variables,
        NotificationLog $log
    ): array {
        $notification = new \App\Notifications\TemplatedNotification(
            $template,
            $variables,
            NotificationTemplate::CHANNEL_DATABASE
        );

        $user->notify($notification);
        
        $log->update(['notification_id' => $notification->id]);
        $log->markAsSent();
        $log->markAsDelivered();

        return [
            'success' => true,
            'notification_id' => $notification->id,
            'message' => 'Database notification sent successfully',
        ];
    }

    /**
     * Send email notification.
     */
    protected function sendEmailNotification(
        User $user,
        NotificationTemplate $template,
        array $variables,
        NotificationLog $log
    ): array {
        if (!$user->email) {
            throw new \Exception('User has no email address');
        }

        $notification = new \App\Notifications\TemplatedNotification(
            $template,
            $variables,
            NotificationTemplate::CHANNEL_EMAIL
        );

        $user->notify($notification);
        
        $log->markAsSent();

        return [
            'success' => true,
            'message' => 'Email notification queued successfully',
        ];
    }

    /**
     * Send SMS notification.
     */
    protected function sendSmsNotification(
        User $user,
        NotificationTemplate $template,
        array $variables,
        NotificationLog $log
    ): array {
        if (!$user->phone) {
            throw new \Exception('User has no phone number');
        }

        // This would integrate with SMS service like Twilio
        // For now, we'll simulate the functionality
        
        $message = $template->render(NotificationTemplate::CHANNEL_SMS, $variables);
        
        if (!$message) {
            throw new \Exception('No SMS template configured');
        }

        // Simulate SMS sending
        $log->markAsSent(['sms_id' => 'sim_' . uniqid()]);
        $log->markAsDelivered();

        return [
            'success' => true,
            'message' => 'SMS notification sent successfully',
            'content' => $message,
        ];
    }

    /**
     * Send push notification.
     */
    protected function sendPushNotification(
        User $user,
        NotificationTemplate $template,
        array $variables,
        NotificationLog $log
    ): array {
        // This would integrate with FCM or similar service
        // For now, we'll simulate the functionality
        
        $message = $template->render(NotificationTemplate::CHANNEL_PUSH, $variables);
        
        if (!$message) {
            throw new \Exception('No push template configured');
        }

        $log->markAsSent(['push_id' => 'push_' . uniqid()]);
        $log->markAsDelivered();

        return [
            'success' => true,
            'message' => 'Push notification sent successfully',
            'content' => $message,
        ];
    }

    /**
     * Send Slack notification.
     */
    protected function sendSlackNotification(
        User $user,
        NotificationTemplate $template,
        array $variables,
        NotificationLog $log
    ): array {
        // This would integrate with Slack API
        // For now, we'll simulate the functionality
        
        $message = $template->render(NotificationTemplate::CHANNEL_DATABASE, $variables); // Use database template as fallback
        
        $log->markAsSent(['slack_channel' => 'general']);
        $log->markAsDelivered();

        return [
            'success' => true,
            'message' => 'Slack notification sent successfully',
            'content' => $message,
        ];
    }

    /**
     * Send webhook notification.
     */
    protected function sendWebhookNotification(
        User $user,
        NotificationTemplate $template,
        array $variables,
        NotificationLog $log
    ): array {
        // This would send HTTP POST to configured webhook URL
        // For now, we'll simulate the functionality
        
        $payload = [
            'user_id' => $user->id,
            'template' => $template->name,
            'variables' => $variables,
            'timestamp' => now()->toISOString(),
        ];

        $log->markAsSent(['webhook_url' => 'https://example.com/webhook']);
        $log->markAsDelivered();

        return [
            'success' => true,
            'message' => 'Webhook notification sent successfully',
            'payload' => $payload,
        ];
    }

    /**
     * Queue notification for digest.
     */
    protected function queueForDigest(
        User $user,
        NotificationTemplate $template,
        array $variables,
        string $frequency
    ): array {
        // This would add to digest queue
        // For now, we'll just log it
        
        Log::info("Notification queued for digest", [
            'user_id' => $user->id,
            'template' => $template->name,
            'frequency' => $frequency,
        ]);

        return [
            'sent' => true,
            'queued_for_digest' => true,
            'frequency' => $frequency,
            'channels' => [],
        ];
    }

    /**
     * Get recipient identifier for channel.
     */
    protected function getRecipientForChannel(User $user, string $channel): string
    {
        return match ($channel) {
            NotificationTemplate::CHANNEL_EMAIL => $user->email ?? '',
            NotificationTemplate::CHANNEL_SMS => $user->phone ?? '',
            NotificationTemplate::CHANNEL_DATABASE => "user:{$user->id}",
            default => "user:{$user->id}",
        };
    }

    /**
     * Send bulk notification to multiple users.
     */
    public function sendBulk(
        array $userIds,
        string $templateName,
        array $variables = [],
        array $options = []
    ): array {
        $batchSize = $options['batch_size'] ?? 100;
        $delay = $options['delay'] ?? 0; // seconds between batches
        
        $batches = array_chunk($userIds, $batchSize);
        $results = [
            'total_users' => count($userIds),
            'batches' => count($batches),
            'queued_at' => now()->toISOString(),
        ];

        foreach ($batches as $index => $batch) {
            Queue::later(
                now()->addSeconds($delay * $index),
                new \App\Jobs\SendBulkNotificationJob($batch, $templateName, $variables)
            );
        }

        return $results;
    }

    /**
     * Get user's notification preferences summary.
     */
    public function getUserPreferences(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $settings = UserNotificationSettings::getForUser($userId);
        $preferences = NotificationPreference::where('user_id', $userId)
            ->with('template')
            ->get()
            ->keyBy('template.name');

        $templates = NotificationTemplate::active()->get();

        $result = [
            'user_settings' => $settings->getSummary(),
            'templates' => [],
        ];

        foreach ($templates as $template) {
            $preference = $preferences->get($template->name);
            
            $result['templates'][$template->name] = [
                'template' => [
                    'title' => $template->title,
                    'category' => $template->category,
                    'available_channels' => $template->channels,
                    'default_channels' => $template->default_channels,
                    'is_system' => $template->is_system,
                ],
                'preference' => $preference ? [
                    'enabled' => $preference->is_enabled,
                    'enabled_channels' => $preference->enabled_channels,
                    'frequency' => $preference->frequency,
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * Update user notification preferences.
     */
    public function updateUserPreferences(int $userId, array $preferences): void
    {
        foreach ($preferences as $templateName => $settings) {
            $template = NotificationTemplate::where('name', $templateName)->first();
            if (!$template) {
                continue;
            }

            NotificationPreference::setForUser(
                $userId,
                $template->id,
                $settings['enabled_channels'] ?? $template->default_channels ?? [],
                $settings['is_enabled'] ?? true,
                $settings['frequency'] ?? NotificationPreference::FREQUENCY_IMMEDIATE
            );
        }
    }

    /**
     * Update user global notification settings.
     */
    public function updateUserSettings(int $userId, array $settings): void
    {
        $userSettings = UserNotificationSettings::getForUser($userId);
        $userSettings->update($settings);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(string $notificationId, int $userId): bool
    {
        $notification = \Illuminate\Notifications\DatabaseNotification::where('id', $notificationId)
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', User::class)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();

        // Update log if exists
        $log = NotificationLog::where('notification_id', $notificationId)->first();
        if ($log) {
            $log->markAsOpened();
        }

        return true;
    }

    /**
     * Get notification statistics for user.
     */
    public function getUserStats(int $userId, int $days = 30): array
    {
        $start = now()->subDays($days);
        $end = now();

        $logs = NotificationLog::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end]);

        $unreadCount = \Illuminate\Notifications\DatabaseNotification::where('notifiable_id', $userId)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();

        return [
            'period_days' => $days,
            'total_sent' => $logs->count(),
            'by_channel' => $logs->clone()->groupBy('channel')->map->count()->toArray(),
            'by_status' => $logs->clone()->groupBy('status')->map->count()->toArray(),
            'unread_count' => $unreadCount,
            'delivery_rate' => $logs->count() > 0 ? 
                round($logs->clone()->delivered()->count() / $logs->count() * 100, 2) : 0,
        ];
    }

    /**
     * Test notification system.
     */
    public function test(int $userId, string $channel = 'database'): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        return $this->send(
            $user,
            'system_test',
            [
                'user_name' => $user->name,
                'test_time' => now()->format('Y-m-d H:i:s'),
            ],
            [$channel]
        );
    }
}