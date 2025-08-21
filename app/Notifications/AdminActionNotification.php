<?php

namespace App\Notifications;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected AdminAuditLog $auditLog,
        protected string $notificationType = 'admin_action'
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $adminUser = $this->auditLog->adminUser;
        
        return (new MailMessage)
            ->subject($this->getSubject())
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getMainMessage())
            ->when($this->shouldIncludeDetails(), function (MailMessage $message) {
                return $message->line('**Details:**')
                              ->line("Action: {$this->auditLog->action}")
                              ->line("Description: {$this->auditLog->description}")
                              ->line("Performed at: {$this->auditLog->created_at->format('M j, Y \a\t g:i A')}")
                              ->line("IP Address: {$this->auditLog->ip_address}");
            })
            ->when($this->shouldIncludeActionButton(), function (MailMessage $message) {
                return $message->action(
                    'View Audit Logs',
                    route('admin.audit_logs', ['admin_user_id' => $this->auditLog->admin_user_id])
                );
            })
            ->line('If you did not authorize this action, please contact your system administrator immediately.')
            ->salutation('Best regards, ' . config('app.name') . ' Security Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'audit_log_id' => $this->auditLog->id,
            'action' => $this->auditLog->action,
            'description' => $this->auditLog->description,
            'admin_user_id' => $this->auditLog->admin_user_id,
            'admin_user_name' => $this->auditLog->adminUser->name,
            'severity' => $this->auditLog->severity,
            'performed_at' => $this->auditLog->created_at->toISOString(),
        ];
    }

    /**
     * Get the notification subject.
     */
    protected function getSubject(): string
    {
        $severity = ucfirst($this->auditLog->severity);
        $action = $this->getActionLabel();
        
        return "[$severity] Admin Action: $action";
    }

    /**
     * Get the main message for the notification.
     */
    protected function getMainMessage(): string
    {
        $adminUser = $this->auditLog->adminUser;
        $action = $this->getActionLabel();
        
        return "An admin action was performed on your account by {$adminUser->name} ({$adminUser->email}): {$action}";
    }

    /**
     * Get a human-readable action label.
     */
    protected function getActionLabel(): string
    {
        return match($this->auditLog->action) {
            'user_created' => 'User account created',
            'user_updated' => 'User account updated',
            'user_suspended' => 'User account suspended',
            'user_reactivated' => 'User account reactivated',
            'user_deleted' => 'User account deleted',
            'user_password_reset' => 'Password reset',
            'user_impersonated' => 'User account impersonated',
            'user_role_assigned' => 'Admin role assigned',
            'user_role_removed' => 'Admin role removed',
            default => str_replace('_', ' ', title_case($this->auditLog->action)),
        };
    }

    /**
     * Determine if details should be included in the email.
     */
    protected function shouldIncludeDetails(): bool
    {
        return in_array($this->auditLog->severity, ['warning', 'critical']);
    }

    /**
     * Determine if an action button should be included.
     */
    protected function shouldIncludeActionButton(): bool
    {
        return $this->auditLog->severity === 'critical';
    }
}