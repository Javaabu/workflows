---
title: Actions
sidebar_position: 5
---

Actions are what a workflow does by itself: they run when a step is entered (a task's once it's assigned) or when a transition is taken.

```php
Step::end('approved')
    ->runs('update_model', ['attributes' => ['status' => 'approved', 'approved_days' => '{{ approved_days }}']])
    ->runs('notify', ['to' => [['type' => 'starter']], 'title' => ['en' => 'Approved: {{ subject.title }}', 'dv' => 'ގަބޫލުކުރެވިއްޖެ: {{ subject.title }}']]);

Transition::make('approve')->from('review')->to('approved')
    ->runs('webhook', ['url' => 'https://hr.example.com/hooks/leave', 'secret' => env('HR_WEBHOOK_SECRET')]);
```

# Placeholders

Text settings take `{{ days }}`, `{{ data.items.0.name }}`, `{{ subject.title }}` and `{{ starter.name }}`. A setting that's only a placeholder (`'{{ days }}'`) keeps the value's type.

# Built-in actions

| Key | Does | Settings |
| --- | --- | --- |
| `notify` | Sends a notification. See [Notifications](../advanced-usage/notifications.md). | `to`: a list of [assignees](assignees.md), plus `{"type": "assignees"}` for the current task's assignees; `title`, `message` (text or locale maps); `channels` (`database`, `mail`, `sms`; default database and mail) |
| `update_model` | Sets attributes on the subject and saves it. Only fillable attributes change. | `attributes`: attribute => value |
| `set_data` | Sets values in the instance's data, for later conditions and actions. | `values`: key => value |
| `webhook` | Calls a URL, from the queue, after the transition is saved. | `url`, `method` (POST, PUT, PATCH, GET), `headers`, `payload` (default: the workflow, instance, status, step, transition, subject and data), `secret` (signs the body: `X-Workflows-Signature: sha256=<hmac>`) |

The builder shows each action's settings as inputs, and the validator checks them.

# When an action fails

With `workflows.actions_on_failure` = `continue` (the default), a failing action is recorded in the history (`action_failed`, with the message), `ActionFailed` fires, the exception is reported, and the instance goes on. With `throw`, the exception undoes the whole start or transition.

# Queued actions

An action class that implements `ShouldQueue` (like `webhook`) runs as a `RunWorkflowAction` job after the transaction commits, so a slow endpoint doesn't hold anyone up. Its failure is recorded the same way.

Write your own in [Custom actions](../advanced-usage/custom-actions.md).
