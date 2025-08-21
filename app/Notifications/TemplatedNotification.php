<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemplatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected NotificationTemplate $template;
    protected array $variables;
    protected array $channels;

    public function __construct(NotificationTemplate $template, array $variables = [], array $channels = null)
    {
        $this->template = $template;
        $this->variables = $variables;
        $this->channels = $channels ?? $template->channels ?? ['database'];
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $availableChannels = [];

        foreach ($this->channels as $channel) {
            switch ($channel) {
                case NotificationTemplate::CHANNEL_DATABASE:
                    $availableChannels[] = 'database';
                    break;
                case NotificationTemplate::CHANNEL_EMAIL:
                    if ($notifiable->email) {
                        $availableChannels[] = 'mail';
                    }
                    break;
                case NotificationTemplate::CHANNEL_SMS:
                    if ($notifiable->phone) {
                        $availableChannels[] = 'nexmo'; // or your SMS channel
                    }
                    break;
                case NotificationTemplate::CHANNEL_SLACK:
                    $availableChannels[] = 'slack';
                    break;
            }
        }

        return $availableChannels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $subject = $this->template->renderSubject($this->variables);
        $content = $this->template->render(NotificationTemplate::CHANNEL_EMAIL, $this->variables);

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!")
            ->line($content);

        // Add action button if URL is provided in variables
        if (isset($this->variables['action_url']) && isset($this->variables['action_text'])) {
            $message->action($this->variables['action_text'], $this->variables['action_url']);
        }

        return $message->line('Thank you for using ' . config('app.name') . '!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable): array
    {
        $content = $this->template->render(NotificationTemplate::CHANNEL_DATABASE, $this->variables);
        
        return [
            'template_name' => $this->template->name,
            'title' => $this->template->title,
            'content' => $content,
            'category' => $this->template->category,
            'priority' => $this->template->priority,
            'variables' => $this->variables,
            'action_url' => $this->variables['action_url'] ?? null,
            'action_text' => $this->variables['action_text'] ?? null,
            'image_url' => $this->variables['image_url'] ?? null,
            'expires_at' => isset($this->variables['expires_hours']) ? 
                now()->addHours($this->variables['expires_hours'])->toISOString() : null,
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack($notifiable)
    {
        // This would be implemented if using Slack notifications
        // Return slack message format
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toNexmo($notifiable)
    {
        $content = $this->template->render(NotificationTemplate::CHANNEL_SMS, $this->variables);
        
        return [
            'content' => $content,
            'unicode' => false,
        ];
    }

    /**
     * Determine if the notification should be sent.
     */
    public function shouldSend($notifiable, $channel): bool
    {
        // Check if template conditions are met
        $context = array_merge($this->variables, [
            'user_id' => $notifiable->id,
            'user_email' => $notifiable->email,
            'user_created_at' => $notifiable->created_at,
            'current_time' => now(),
        ]);

        return $this->template->conditionsAreMet($context);
    }

    /**
     * Get notification tags for queue management.
     */
    public function tags(): array
    {
        return [
            'template:' . $this->template->name,
            'category:' . $this->template->category,
            'priority:' . $this->template->priority,
        ];
    }

    /**
     * Determine the queue connection for the notification.
     */
    public function viaConnections(): array
    {
        // Use different queues based on priority
        $connection = match ($this->template->priority) {
            NotificationTemplate::PRIORITY_CRITICAL => 'high',
            NotificationTemplate::PRIORITY_HIGH => 'default',
            default => 'low',
        };

        return [
            'mail' => $connection,
            'database' => $connection,
            'nexmo' => $connection,
        ];
    }

    /**
     * Determine the queue for the notification.
     */
    public function viaQueues(): array
    {
        $queue = match ($this->template->priority) {
            NotificationTemplate::PRIORITY_CRITICAL => 'notifications-critical',
            NotificationTemplate::PRIORITY_HIGH => 'notifications-high',
            default => 'notifications',
        };

        return [
            'mail' => $queue,
            'database' => $queue,
            'nexmo' => $queue,
        ];
    }
}