<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable): string
    {
        $hash = sha1($notifiable->getEmailForVerification());
        
        return route('verification.verify', [
            'id' => $notifiable->getKey(),
            'hash' => $hash,
            'token' => $notifiable->email_verification_token,
        ]);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email Address - ' . config('app.name'))
            ->greeting('Welcome to ' . config('app.name') . '!')
            ->line('Thank you for registering with us. Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in 24 hours for security reasons.')
            ->line('If you did not create an account, no further action is required. Your email address will not be used.')
            ->salutation('Best regards,<br>' . config('app.name') . ' Team')
            ->with([
                'user_name' => $notifiable->first_name ?? $notifiable->name,
                'app_name' => config('app.name'),
            ]);
    }
}