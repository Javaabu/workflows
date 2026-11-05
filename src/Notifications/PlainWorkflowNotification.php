<?php

namespace Javaabu\Workflows\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The notify action's notification without javaabu/notifications: Laravel's
 * mail and database channels
 */
class PlainWorkflowNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public WorkflowMessage $workflow_message,
        public array $channels = ['database', 'mail'],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return array_values(array_filter($this->channels, function (string $channel) use ($notifiable) {
            return $channel !== 'mail' || ! method_exists($notifiable, 'routeNotificationFor') || $notifiable->routeNotificationFor('mail', $this);
        }));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->workflow_message->title());

        if ($this->workflow_message->message()) {
            $mail->line($this->workflow_message->message());
        }

        if ($this->workflow_message->url) {
            $mail->action(__('Open'), $this->workflow_message->url);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->workflow_message->title(),
            'message' => $this->workflow_message->message(),
            'url' => $this->workflow_message->url,
            ...$this->workflow_message->data(),
        ];
    }
}
