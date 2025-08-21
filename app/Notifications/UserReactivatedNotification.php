<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserReactivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $adminUser
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
            ->subject('Account Reactivated - ' . config('app.name'))
            ->success()
            ->greeting("Hello {$notifiable->name},")
            ->line('We are pleased to inform you that your account has been reactivated.')
            ->line("**Reactivated by:** {$this->adminUser->name}")
            ->line("**Reactivated at:** " . now()->format('M j, Y \a\t g:i A'))
            ->line('You now have full access to your account and all our services.')
            ->line('You can log in to your account using your existing credentials.')
            ->action('Log In to Your Account', route('login'))
            ->line('If you experience any issues accessing your account, please contact our support team.')
            ->line('Thank you for your patience during the suspension period.')
            ->salutation('Welcome back, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_reactivated',
            'admin_user_id' => $this->adminUser->id,
            'admin_user_name' => $this->adminUser->name,
            'reactivated_at' => now()->toISOString(),
        ];
    }
}