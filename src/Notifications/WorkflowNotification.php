<?php

namespace Javaabu\Workflows\Notifications;

use Javaabu\Notifications\BaseNotification;
use Javaabu\Notifications\Messages\NotificationMessage;

/**
 * The notify action's notification with javaabu/notifications: every channel
 * the notifiable can receive, the bell, preferences. PHP only loads it when
 * the package is installed (see WorkflowNotifications).
 */
class WorkflowNotification extends BaseNotification
{
    protected ?string $icon = 'bell';

    /**
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public WorkflowMessage $workflow_message,
        array $channels = ['database', 'mail'],
    ) {
        $this->channels = $channels;
        $this->color = $workflow_message->color;
    }

    public static function label(): string
    {
        return __('Workflow updates');
    }

    public function message(object $notifiable): NotificationMessage
    {
        $message = NotificationMessage::make($this->workflow_message->title())
            ->line($this->workflow_message->message())
            ->subject($this->workflow_message->instance)
            ->data($this->workflow_message->data());

        if ($this->workflow_message->url) {
            $message->action(__('Open'), $this->workflow_message->url);
        }

        return $message;
    }
}
