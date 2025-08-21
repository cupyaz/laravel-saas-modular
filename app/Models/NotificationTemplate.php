<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'title',
        'category',
        'channels',
        'default_channels',
        'subject',
        'email_template',
        'sms_template',
        'push_template',
        'database_template',
        'variables',
        'is_system',
        'is_active',
        'priority',
        'conditions',
    ];

    protected $casts = [
        'channels' => 'array',
        'default_channels' => 'array',
        'variables' => 'array',
        'conditions' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    // Category constants
    public const CATEGORY_SUBSCRIPTION = 'subscription';
    public const CATEGORY_SUPPORT = 'support';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_MARKETING = 'marketing';

    // Priority constants
    public const PRIORITY_CRITICAL = 1;
    public const PRIORITY_HIGH = 2;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_LOW = 8;

    // Channel constants
    public const CHANNEL_DATABASE = 'database';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_WEBHOOK = 'webhook';

    /**
     * Get user preferences for this template.
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'template_id');
    }

    /**
     * Get notification logs for this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'template_name', 'name');
    }

    /**
     * Scope for active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for system templates.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope for user-configurable templates.
     */
    public function scopeUserConfigurable($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', '<=', $priority);
    }

    /**
     * Check if template supports a channel.
     */
    public function supportsChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? []);
    }

    /**
     * Check if channel is enabled by default.
     */
    public function isChannelEnabledByDefault(string $channel): bool
    {
        return in_array($channel, $this->default_channels ?? []);
    }

    /**
     * Get template content for specific channel.
     */
    public function getContentForChannel(string $channel): ?string
    {
        return match ($channel) {
            self::CHANNEL_EMAIL => $this->email_template,
            self::CHANNEL_SMS => $this->sms_template,
            self::CHANNEL_PUSH => $this->push_template,
            self::CHANNEL_DATABASE => $this->database_template,
            default => null,
        };
    }

    /**
     * Render template with variables.
     */
    public function render(string $channel, array $variables = []): string
    {
        $template = $this->getContentForChannel($channel);
        
        if (!$template) {
            return '';
        }

        return $this->processTemplate($template, $variables);
    }

    /**
     * Render subject with variables.
     */
    public function renderSubject(array $variables = []): string
    {
        if (!$this->subject) {
            return $this->title;
        }

        return $this->processTemplate($this->subject, $variables);
    }

    /**
     * Process template with variables.
     */
    private function processTemplate(string $template, array $variables = []): string
    {
        // Add common variables
        $commonVariables = [
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'date' => now()->format('F j, Y'),
            'time' => now()->format('g:i A'),
        ];

        $allVariables = array_merge($commonVariables, $variables);

        // Simple variable replacement
        foreach ($allVariables as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace("{{$key}}", $value, $template);
                $template = str_replace("{{{$key}}}", $value, $template);
            }
        }

        return $template;
    }

    /**
     * Check if template conditions are met.
     */
    public function conditionsAreMet(array $context = []): bool
    {
        if (!$this->conditions) {
            return true;
        }

        // Simple condition checking - can be extended
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (!$field || !isset($context[$field])) {
                continue;
            }

            $contextValue = $context[$field];

            $result = match ($operator) {
                '=' => $contextValue == $value,
                '!=' => $contextValue != $value,
                '>' => $contextValue > $value,
                '<' => $contextValue < $value,
                '>=' => $contextValue >= $value,
                '<=' => $contextValue <= $value,
                'in' => in_array($contextValue, (array) $value),
                'not_in' => !in_array($contextValue, (array) $value),
                default => true,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_SUBSCRIPTION => 'Subscription',
            self::CATEGORY_SUPPORT => 'Support',
            self::CATEGORY_SECURITY => 'Security',
            self::CATEGORY_BILLING => 'Billing',
            self::CATEGORY_SYSTEM => 'System',
            self::CATEGORY_MARKETING => 'Marketing',
        ];
    }

    /**
     * Get all available channels.
     */
    public static function getChannels(): array
    {
        return [
            self::CHANNEL_DATABASE => 'In-App',
            self::CHANNEL_EMAIL => 'Email',
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_PUSH => 'Push Notification',
            self::CHANNEL_SLACK => 'Slack',
            self::CHANNEL_WEBHOOK => 'Webhook',
        ];
    }

    /**
     * Get all available priorities.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_CRITICAL => 'Critical',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_LOW => 'Low',
        ];
    }

    /**
     * Create default templates.
     */
    public static function createDefaults(): void
    {
        $templates = [
            // Subscription notifications
            [
                'name' => 'subscription_created',
                'title' => 'Subscription Created',
                'category' => self::CATEGORY_SUBSCRIPTION,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'Welcome to {app_name}! Your subscription is now active',
                'email_template' => 'Hi {user_name}, your {plan_name} subscription is now active. Thank you for choosing {app_name}!',
                'database_template' => 'Your {plan_name} subscription is now active',
                'variables' => ['user_name', 'plan_name', 'amount'],
                'is_system' => false,
                'priority' => self::PRIORITY_HIGH,
            ],
            [
                'name' => 'subscription_cancelled',
                'title' => 'Subscription Cancelled',
                'category' => self::CATEGORY_SUBSCRIPTION,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'Your subscription has been cancelled',
                'email_template' => 'Hi {user_name}, your subscription has been cancelled. We\'re sorry to see you go!',
                'database_template' => 'Your subscription has been cancelled',
                'variables' => ['user_name', 'plan_name', 'cancellation_date'],
                'is_system' => false,
                'priority' => self::PRIORITY_NORMAL,
            ],

            // Support notifications
            [
                'name' => 'support_ticket_created',
                'title' => 'Support Ticket Created',
                'category' => self::CATEGORY_SUPPORT,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'Support ticket #{ticket_number} created',
                'email_template' => 'Hi {user_name}, your support ticket #{ticket_number} has been created. We\'ll respond within 24 hours.',
                'database_template' => 'Support ticket #{ticket_number} created',
                'variables' => ['user_name', 'ticket_number', 'ticket_title'],
                'is_system' => false,
                'priority' => self::PRIORITY_NORMAL,
            ],
            [
                'name' => 'support_ticket_replied',
                'title' => 'Support Ticket Reply',
                'category' => self::CATEGORY_SUPPORT,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL, self::CHANNEL_PUSH],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'New reply on ticket #{ticket_number}',
                'email_template' => 'Hi {user_name}, there\'s a new reply on your ticket #{ticket_number}.',
                'database_template' => 'New reply on ticket #{ticket_number}',
                'push_template' => 'New reply on your support ticket',
                'variables' => ['user_name', 'ticket_number', 'agent_name'],
                'is_system' => false,
                'priority' => self::PRIORITY_HIGH,
            ],

            // Security notifications
            [
                'name' => 'security_login_suspicious',
                'title' => 'Suspicious Login Detected',
                'category' => self::CATEGORY_SECURITY,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL, self::CHANNEL_SMS],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'Suspicious login detected on your account',
                'email_template' => 'Hi {user_name}, we detected a suspicious login to your account from {location} at {login_time}.',
                'database_template' => 'Suspicious login detected from {location}',
                'sms_template' => 'Suspicious login detected on your {app_name} account. If this wasn\'t you, secure your account immediately.',
                'variables' => ['user_name', 'location', 'login_time', 'ip_address'],
                'is_system' => true,
                'priority' => self::PRIORITY_CRITICAL,
            ],

            // Billing notifications
            [
                'name' => 'payment_successful',
                'title' => 'Payment Successful',
                'category' => self::CATEGORY_BILLING,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'Payment received - ${amount}',
                'email_template' => 'Hi {user_name}, we\'ve received your payment of ${amount} for your {plan_name} subscription.',
                'database_template' => 'Payment of ${amount} received',
                'variables' => ['user_name', 'amount', 'plan_name', 'invoice_number'],
                'is_system' => false,
                'priority' => self::PRIORITY_NORMAL,
            ],
            [
                'name' => 'payment_failed',
                'title' => 'Payment Failed',
                'category' => self::CATEGORY_BILLING,
                'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL, self::CHANNEL_SMS],
                'default_channels' => [self::CHANNEL_DATABASE, self::CHANNEL_EMAIL],
                'subject' => 'Payment failed - Action required',
                'email_template' => 'Hi {user_name}, your payment of ${amount} failed. Please update your payment method to avoid service interruption.',
                'database_template' => 'Payment of ${amount} failed - Update payment method',
                'sms_template' => 'Your {app_name} payment failed. Update your payment method to avoid service interruption.',
                'variables' => ['user_name', 'amount', 'failure_reason'],
                'is_system' => true,
                'priority' => self::PRIORITY_HIGH,
            ],
        ];

        foreach ($templates as $template) {
            self::create($template);
        }
    }

    /**
     * Get usage statistics.
     */
    public function getUsageStats(): array
    {
        $logs = $this->logs()->where('created_at', '>=', now()->subDays(30));

        return [
            'total_sent' => $logs->count(),
            'success_rate' => $logs->where('status', 'delivered')->count() / max($logs->count(), 1) * 100,
            'channels_usage' => $logs->groupBy('channel')->map->count()->toArray(),
            'recent_performance' => $logs->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}