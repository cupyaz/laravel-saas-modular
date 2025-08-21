<?php

namespace App\Notifications;

use App\Models\UsageAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class UsageLimitAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected UsageAlert $alert;

    public function __construct(UsageAlert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        
        // Add additional channels based on alert severity
        if ($this->alert->severity === 'critical') {
            $channels[] = 'slack';
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $feature = ucfirst(str_replace('_', ' ', $this->alert->feature));
        $metric = ucfirst(str_replace('_', ' ', $this->alert->metric));
        
        $subject = $this->getEmailSubject();
        $greeting = $this->getEmailGreeting();
        
        return (new MailMessage)
                    ->subject($subject)
                    ->greeting($greeting)
                    ->line($this->alert->getAlertMessage())
                    ->line($this->getUsageDetails())
                    ->when($this->shouldShowUpgradeOption(), function (MailMessage $mail) {
                        return $mail->action('Upgrade Your Plan', $this->getUpgradeUrl());
                    })
                    ->line('You can view detailed usage information in your dashboard.')
                    ->action('View Usage Dashboard', $this->getDashboardUrl())
                    ->line('If you have any questions, please contact our support team.')
                    ->salutation('Best regards, The ' . config('app.name') . ' Team');
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $feature = ucfirst(str_replace('_', ' ', $this->alert->feature));
        $metric = ucfirst(str_replace('_', ' ', $this->alert->metric));
        
        return (new SlackMessage)
                    ->error()
                    ->content('🚨 Critical Usage Alert')
                    ->attachment(function ($attachment) use ($feature, $metric) {
                        $attachment->title('Usage Limit Alert')
                                  ->fields([
                                      'Tenant' => $this->alert->tenant->name,
                                      'Feature' => $feature,
                                      'Metric' => $metric,
                                      'Alert Type' => ucfirst($this->alert->alert_type),
                                      'Current Usage' => $this->alert->getFormattedUsage(),
                                      'Limit' => $this->alert->getFormattedLimit(),
                                      'Percentage' => number_format($this->alert->getCurrentPercentage(), 1) . '%',
                                  ])
                                  ->color($this->getSlackColor());
                    });
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'tenant_id' => $this->alert->tenant_id,
            'feature' => $this->alert->feature,
            'metric' => $this->alert->metric,
            'alert_type' => $this->alert->alert_type,
            'severity' => $this->alert->severity,
            'current_usage' => $this->alert->current_usage,
            'limit_value' => $this->alert->limit_value,
            'percentage_used' => $this->alert->getCurrentPercentage(),
            'message' => $this->alert->getAlertMessage(),
            'created_at' => $this->alert->created_at,
        ];
    }

    private function getEmailSubject(): string
    {
        $feature = ucfirst(str_replace('_', ' ', $this->alert->feature));
        
        return match ($this->alert->alert_type) {
            'warning' => "⚠️ Usage Warning: {$feature} usage is approaching your limit",
            'soft_limit' => "🔶 Soft Limit Reached: {$feature} usage exceeded warning threshold",
            'hard_limit' => "🛑 Hard Limit Reached: {$feature} usage limit exceeded",
            'overage' => "📊 Usage Overage: {$feature} usage has exceeded your plan limits",
            default => "📈 Usage Alert: {$feature}",
        };
    }

    private function getEmailGreeting(): string
    {
        return match ($this->alert->severity) {
            'critical' => 'Urgent: Action Required',
            'high' => 'Important Notice',
            'medium' => 'Usage Update',
            'low' => 'Usage Notification',
            default => 'Hello',
        };
    }

    private function getUsageDetails(): string
    {
        $details = "**Usage Details:**\n";
        $details .= "• Current Usage: {$this->alert->getFormattedUsage()}\n";
        $details .= "• Plan Limit: {$this->alert->getFormattedLimit()}\n";
        $details .= "• Percentage Used: " . number_format($this->alert->getCurrentPercentage(), 1) . "%\n";
        
        if ($this->alert->alert_type === 'overage') {
            $overageAmount = $this->alert->current_usage - $this->alert->limit_value;
            $details .= "• Overage Amount: " . number_format($overageAmount, 2) . "\n";
        }
        
        return $details;
    }

    private function shouldShowUpgradeOption(): bool
    {
        return in_array($this->alert->alert_type, ['soft_limit', 'hard_limit', 'overage']) &&
               $this->alert->canAutoUpgrade();
    }

    private function getUpgradeUrl(): string
    {
        return url('/subscription/upgrade');
    }

    private function getDashboardUrl(): string
    {
        return url('/dashboard/usage');
    }

    private function getSlackColor(): string
    {
        return match ($this->alert->severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'warning',
            'low' => 'good',
            default => 'warning',
        };
    }
}