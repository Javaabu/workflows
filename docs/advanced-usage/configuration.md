---
title: Configuration
sidebar_position: 1
---

Publish `config/workflows.php` with `php artisan vendor:publish --tag=workflows-config`. Every option has a default.

| Option | Default | |
| --- | --- | --- |
| `actions` | the four built-in actions | action classes the builder offers and definitions can use |
| `actions_on_failure` | `continue` | `continue` (record, report, go on) or `throw` (undo the start or transition) |
| `notifications.javaabu` | `true` | use javaabu/notifications' `BaseNotification` when it's installed |
| `notifications.class` | `null` | your own notification class for the notify action |
| `webhooks.timeout` | `10` | seconds before a webhook gives up |
| `max_automatic_steps` | `100` | how many automatic steps in a row before the engine stops a loop |
| `starters_can_cancel` | `true` | whether starters can cancel their own instances |
| `sync.publish` | `true` | whether `sync()` publishes the version it makes |
| `models.*` | the package's models | `workflow` (null: `Models\Translatable\Workflow` with translatable on), `version`, `instance`, `task`, `assignee`, `history` |
| `tables.*` | `workflows`, `workflow_versions`, … | table names |
| `views` | `blade` (`WORKFLOWS_VIEWS`) | `blade` or `inertia` |
| `layout`, `layout_section` | `layouts.app`, `content` | what the Blade pages extend and fill |
| `per_page` | `20` | the length of lists |
| `datetime_format` | `j F Y, H:i` | how dates show (translated) |
| `subjects` | `[]` | morph alias or class => label, for the settings page's subject list; empty shows a text input |
| `instance_route` | `null` | the route notifications link to for an instance |
| `controllers.*` | the package's controllers | `workflows`, `tester`, `instances`, `tasks`, `start`, `assistant` |
| `policies.*` | `WorkflowPolicy`, `WorkflowInstancePolicy` | `workflow`, `instance` |
| `register_permissions` | `true` | register the permissions with javaabu/permissions |
| `assistant.enabled` | `true` (`WORKFLOWS_ASSISTANT`) | the AI assistant, with laravel/ai installed |
| `assistant.provider`, `assistant.model` | `null` | laravel/ai's provider and model (null: its defaults) |
| `assistant.timeout` | `120` | seconds |
| `assistant.attempts` | `2` | answers it gets to produce a valid workflow |
| `assistant.max_length` | `4000` | the longest description |
