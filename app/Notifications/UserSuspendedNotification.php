<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $reason,
        protected User $adminUser,
        protected bool $isPermanent = false
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
        return (new MailMessage)
            ->subject('Account Suspended - ' . config('app.name'))
            ->error()
            ->greeting("Hello {$notifiable->name},")
            ->line('We are writing to inform you that your account has been suspended.')
            ->line("**Reason:** {$this->reason}")
            ->line("**Suspended by:** {$this->adminUser->name}")
            ->line("**Suspended at:** " . now()->format('M j, Y \a\t g:i A'))
            ->when(!$this->isPermanent, function (MailMessage $message) {
                return $message->line('This suspension may be temporary. You will be notified when your account is reactivated.');
            })
            ->when($this->isPermanent, function (MailMessage $message) {
                return $message->line('This is a permanent suspension.');
            })
            ->line('During the suspension period, you will not be able to access your account or use our services.')
            ->line('If you believe this suspension was made in error or if you have any questions, please contact our support team.')
            ->action('Contact Support', config('app.support_url', 'mailto:support@' . parse_url(config('app.url'), PHP_URL_HOST)))
            ->salutation('Regards, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_suspended',
            'reason' => $this->reason,
            'admin_user_id' => $this->adminUser->id,
            'admin_user_name' => $this->adminUser->name,
            'is_permanent' => $this->isPermanent,
            'suspended_at' => now()->toISOString(),
        ];
    }
}