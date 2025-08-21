<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $temporaryPassword = null,
        protected ?User $createdBy = null
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
            ->subject('Welcome to ' . config('app.name'))
            ->greeting("Welcome {$notifiable->name}!")
            ->line('Your account has been successfully created on ' . config('app.name') . '.')
            ->when($this->createdBy, function (MailMessage $message) {
                return $message->line("Your account was created by {$this->createdBy->name} ({$this->createdBy->email}).");
            })
            ->line('Here are your account details:')
            ->line("**Email:** {$notifiable->email}")
            ->when($this->temporaryPassword, function (MailMessage $message) {
                return $message->line("**Temporary Password:** {$this->temporaryPassword}")
                              ->line('⚠️  **Important:** You will be required to change this password upon your first login.');
            })
            ->when(!$this->temporaryPassword, function (MailMessage $message) {
                return $message->line('You will need to set up your password using the link below.');
            })
            ->line('To get started with your account:')
            ->line('1. Click the button below to access your account')
            ->line('2. Complete your profile setup')
            ->line('3. Explore the platform features')
            ->when($this->temporaryPassword, function (MailMessage $message) {
                return $message->action('Log In to Your Account', route('login'));
            })
            ->when(!$this->temporaryPassword, function (MailMessage $message) use ($notifiable) {
                return $message->action('Set Up Your Password', route('password.reset', ['token' => 'welcome', 'email' => $notifiable->email]));
            })
            ->line('If you have any questions or need assistance, our support team is here to help.')
            ->action('Contact Support', config('app.support_url', 'mailto:support@' . parse_url(config('app.url'), PHP_URL_HOST)))
            ->salutation('Welcome aboard, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome_user',
            'created_by_id' => $this->createdBy?->id,
            'created_by_name' => $this->createdBy?->name,
            'has_temporary_password' => !is_null($this->temporaryPassword),
            'created_at' => now()->toISOString(),
        ];
    }
}