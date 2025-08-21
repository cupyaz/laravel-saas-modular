<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetByAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $adminUser,
        protected ?string $temporaryPassword = null,
        protected bool $forceChange = true
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
            ->subject('Password Reset - ' . config('app.name'))
            ->greeting("Hello {$notifiable->name},")
            ->line('Your password has been reset by an administrator.')
            ->line("**Reset by:** {$this->adminUser->name}")
            ->line("**Reset at:** " . now()->format('M j, Y \a\t g:i A'))
            ->when($this->temporaryPassword, function (MailMessage $message) {
                return $message->line('**Your temporary password is:** ' . $this->temporaryPassword)
                              ->line('⚠️  **Important:** Please keep this password secure and do not share it with anyone.');
            })
            ->when(!$this->temporaryPassword, function (MailMessage $message) {
                return $message->line('You will need to reset your password using the "Forgot Password" link on the login page.');
            })
            ->when($this->forceChange, function (MailMessage $message) {
                return $message->line('**You will be required to change this password** upon your next login for security purposes.');
            })
            ->line('For your security, please log in and change your password as soon as possible.')
            ->action('Log In to Your Account', route('login'))
            ->line('If you did not request this password reset, please contact our support team immediately.')
            ->salutation('Best regards, ' . config('app.name') . ' Security Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset_by_admin',
            'admin_user_id' => $this->adminUser->id,
            'admin_user_name' => $this->adminUser->name,
            'force_change' => $this->forceChange,
            'has_temporary_password' => !is_null($this->temporaryPassword),
            'reset_at' => now()->toISOString(),
        ];
    }
}