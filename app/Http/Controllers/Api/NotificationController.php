<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\NotificationPreference;
use App\Models\NotificationLog;
use App\Models\UserNotificationSettings;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->middleware('auth:sanctum');
        $this->notificationService = $notificationService;
    }

    // ====================
    // USER NOTIFICATIONS
    // ====================

    /**
     * Get user's notifications.
     */
    public function getUserNotifications(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'unread_only' => 'sometimes|boolean',
            'category' => 'sometimes|string',
        ]);

        $user = Auth::user();
        $perPage = $request->input('per_page', 15);

        $query = $user->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('category')) {
            $query->whereJsonContains('data->category', $request->input('category'));
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadCount(): JsonResponse
    {
        $user = Auth::user();
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(string $notificationId): JsonResponse
    {
        $user = Auth::user();
        $success = $this->notificationService->markAsRead($notificationId, $user->id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete notification.
     */
    public function deleteNotification(string $notificationId): JsonResponse
    {
        $user = Auth::user();
        
        $notification = $user->notifications()
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    // ====================
    // NOTIFICATION PREFERENCES
    // ====================

    /**
     * Get user's notification preferences.
     */
    public function getPreferences(): JsonResponse
    {
        $user = Auth::user();
        $preferences = $this->notificationService->getUserPreferences($user->id);

        return response()->json([
            'success' => true,
            'data' => $preferences,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'templates' => 'sometimes|array',
            'templates.*' => 'array',
            'templates.*.is_enabled' => 'boolean',
            'templates.*.enabled_channels' => 'array',
            'templates.*.enabled_channels.*' => Rule::in(array_keys(NotificationTemplate::getChannels())),
            'templates.*.frequency' => Rule::in(array_keys(NotificationPreference::getFrequencies())),
            'settings' => 'sometimes|array',
            'settings.notifications_enabled' => 'boolean',
            'settings.marketing_enabled' => 'boolean',
            'settings.product_updates_enabled' => 'boolean',
            'settings.security_alerts_enabled' => 'boolean',
        ]);

        $user = Auth::user();

        try {
            // Update template preferences
            if ($request->has('templates')) {
                $this->notificationService->updateUserPreferences($user->id, $request->input('templates'));
            }

            // Update global settings
            if ($request->has('settings')) {
                $this->notificationService->updateUserSettings($user->id, $request->input('settings'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update global notification settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'notifications_enabled' => 'sometimes|boolean',
            'global_channels' => 'sometimes|array',
            'global_channels.*' => Rule::in(array_keys(NotificationTemplate::getChannels())),
            'marketing_enabled' => 'sometimes|boolean',
            'product_updates_enabled' => 'sometimes|boolean',
            'security_alerts_enabled' => 'sometimes|boolean',
            'digest_frequency' => ['sometimes', Rule::in(array_keys(UserNotificationSettings::getDigestFrequencies()))],
            'digest_time' => 'sometimes|date_format:H:i',
            'timezone' => 'sometimes|string|timezone',
            'do_not_disturb_enabled' => 'sometimes|boolean',
            'dnd_start_time' => 'sometimes|date_format:H:i',
            'dnd_end_time' => 'sometimes|date_format:H:i',
            'dnd_days' => 'sometimes|array',
        ]);

        $user = Auth::user();
        
        try {
            $this->notificationService->updateUserSettings($user->id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset preferences to defaults.
     */
    public function resetPreferences(): JsonResponse
    {
        $user = Auth::user();
        
        try {
            // Reset all preferences to template defaults
            $preferences = NotificationPreference::where('user_id', $user->id)->get();
            foreach ($preferences as $preference) {
                $preference->resetToDefaults();
            }

            // Reset global settings
            $settings = UserNotificationSettings::getForUser($user->id);
            $settings->update([
                'notifications_enabled' => true,
                'global_channels' => [],
                'marketing_enabled' => true,
                'product_updates_enabled' => true,
                'security_alerts_enabled' => true,
                'digest_frequency' => UserNotificationSettings::DIGEST_DAILY,
                'do_not_disturb_enabled' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preferences reset to defaults',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ====================
    // NOTIFICATION STATISTICS
    // ====================

    /**
     * Get user's notification statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $user = Auth::user();
        $days = $request->input('days', 30);
        
        $stats = $this->notificationService->getUserStats($user->id, $days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    // ====================
    // TEMPLATES (Public)
    // ====================

    /**
     * Get available notification templates.
     */
    public function getTemplates(): JsonResponse
    {
        $templates = NotificationTemplate::active()
            ->userConfigurable()
            ->select(['id', 'name', 'title', 'category', 'channels', 'default_channels', 'is_system'])
            ->get()
            ->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $templates,
            'categories' => NotificationTemplate::getCategories(),
            'channels' => NotificationTemplate::getChannels(),
        ]);
    }

    // ====================
    // TESTING
    // ====================

    /**
     * Test notification system.
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['sometimes', Rule::in(array_keys(NotificationTemplate::getChannels()))],
        ]);

        $user = Auth::user();
        $channel = $request->input('channel', NotificationTemplate::CHANNEL_DATABASE);

        try {
            $result = $this->notificationService->test($user->id, $channel);

            return response()->json([
                'success' => true,
                'message' => 'Test notification sent',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test notification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ====================
    // ADMIN FUNCTIONS
    // ====================

    /**
     * Send bulk notification (Admin only).
     */
    public function sendBulk(Request $request): JsonResponse
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
            'template_name' => 'required|string|exists:notification_templates,name',
            'variables' => 'sometimes|array',
            'batch_size' => 'sometimes|integer|min:1|max:1000',
        ]);

        try {
            $result = $this->notificationService->sendBulk(
                $request->input('user_ids'),
                $request->input('template_name'),
                $request->input('variables', []),
                [
                    'batch_size' => $request->input('batch_size', 100),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk notification queued successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue bulk notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get notification analytics (Admin only).
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'template_name' => 'sometimes|string',
            'channel' => 'sometimes|string',
        ]);

        $startDate = $request->input('start_date') ? 
            \Carbon\Carbon::parse($request->input('start_date')) : 
            now()->subDays(30);
        
        $endDate = $request->input('end_date') ? 
            \Carbon\Carbon::parse($request->input('end_date')) : 
            now();

        try {
            $analytics = [
                'period' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString(),
                ],
                'overview' => NotificationLog::getDeliveryStats($startDate, $endDate),
                'by_channel' => NotificationLog::getChannelStats($startDate, $endDate),
                'by_template' => NotificationLog::getTemplateStats($startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get notification logs (Admin only).
     */
    public function getLogs(Request $request): JsonResponse
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'user_id' => 'sometimes|integer|exists:users,id',
            'template_name' => 'sometimes|string',
            'channel' => 'sometimes|string',
            'status' => ['sometimes', Rule::in(array_keys(NotificationLog::getStatuses()))],
        ]);

        $perPage = $request->input('per_page', 50);

        $query = NotificationLog::with(['user', 'template'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('template_name')) {
            $query->where('template_name', $request->input('template_name'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}