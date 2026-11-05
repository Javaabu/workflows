<?php

namespace Javaabu\Workflows\Notifications;

use Illuminate\Notifications\Notification;
use Javaabu\Notifications\BaseNotification;

/**
 * Makes the notify action's notification: javaabu/notifications' when it's
 * installed, a plain Laravel one otherwise
 */
class WorkflowNotifications
{
    public static function usesJavaabuNotifications(): bool
    {
        return class_exists(BaseNotification::class) && (bool) config('workflows.notifications.javaabu', true);
    }

    /**
     * @param  array<int, string>  $channels
     */
    public static function make(WorkflowMessage $message, array $channels = ['database', 'mail']): Notification
    {
        $class = config('workflows.notifications.class')
            ?? (static::usesJavaabuNotifications() ? WorkflowNotification::class : PlainWorkflowNotification::class);

        return new $class($message, $channels);
    }
}
