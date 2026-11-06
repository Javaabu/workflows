---
title: Notifications
sidebar_position: 6
---

The `notify` action sends a notification to the people in its `to` setting:

```php
Step::task('review')->runs('notify', [
    'to' => [['type' => 'assignees'], ['type' => 'role', 'value' => 'hr']],
    'title' => ['en' => 'A leave request to review', 'dv' => 'ރިވިއުކުރަން ޗުއްޓީއެއް'],
    'message' => '{{ subject.title }}, {{ subject.days }} days',
    'channels' => ['database', 'mail'],
]);
```

`assignees` is the current task's assignees (a task's actions run once it's assigned); everything else is an [assignee](../basic-usage/assignees.md). Roles and permissions are expanded to their users. Only models that can be notified are sent anything.

# With javaabu/notifications

When [javaabu/notifications](https://github.com/Javaabu/notifications) is installed, the notification is `Javaabu\Workflows\Notifications\WorkflowNotification`, a `BaseNotification`: it goes to every channel in `channels` the notifiable can receive and hasn't turned off, in their language, shows in the bell, and links to the instance. Its label in preferences is *Workflow updates*.

# Without it

`PlainWorkflowNotification` sends Laravel's `mail` and `database` notifications (the database row has `title`, `message`, `url`, `workflow`, `workflow_instance_id` and `step`).

# Your own

```php
// config/workflows.php
'notifications' => [
    'javaabu' => true,                                   // false: always the plain notification
    'class' => App\Notifications\WorkflowNotification::class,
],
```

Your class is constructed with a `WorkflowMessage` (`instance`, `title()`, `message()`, `url`, `data()`) and the channels.

The link is `Workflows::instanceUrl($instance)`: the instance page of the first `Route::workflowTasks()`, `workflows.instance_route`, or your `Workflows::instanceUrlUsing()` resolver.
