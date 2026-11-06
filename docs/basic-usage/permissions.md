---
title: Permissions
sidebar_position: 10
---

The package registers these permissions with javaabu/permissions (run `php artisan permissions:sync`):

| Permission | Lets someone |
| --- | --- |
| `view_workflows` | see workflows, the builder (read-only), versions and the tester |
| `edit_workflows` | create workflows and change their settings and drafts (not locked ones) |
| `delete_workflows` | delete workflows (not locked ones) |
| `publish_workflows` | publish drafts (not of locked workflows) |
| `start_workflows` | start published, active workflows |
| `view_workflow_instances` | see every instance of a workflow |
| `manage_workflow_instances` | see every instance, act on any task and cancel any instance |

Without permissions, people still:

- see instances they started or have a task in;
- act on tasks they're assigned to;
- cancel instances they started (unless `workflows.starters_can_cancel` is false).

# The policies

`WorkflowPolicy` (`viewAny`, `view`, `test`, `create`, `update`, `delete`, `publish`, `start`, `viewInstances`) and `WorkflowInstancePolicy` (`view`, `act`, `cancel`) check permissions with `$user->can()`, so they work for any guard and user model. Override them:

```php
// config/workflows.php
'policies' => ['workflow' => App\Policies\WorkflowPolicy::class],

// or in a service provider
Gate::policy(Workflow::class, App\Policies\WorkflowPolicy::class);
```

Turn off `workflows.register_permissions` to register your own permissions instead. See [Controllers and views](../advanced-usage/controllers-and-views.md).
