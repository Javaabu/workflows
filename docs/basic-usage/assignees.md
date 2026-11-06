---
title: Assignees
sidebar_position: 3
---

A task step's assignees decide who can act on its tasks. Give a step one or more; anyone matching any of them can act.

```php
use Javaabu\Workflows\Definitions\Assignee;

Step::task('review')->assignTo(
    Assignee::subject('manager'),     // the subject's manager relation or attribute
    Assignee::role('hr'),
);
```

| Assignee | Value | Who |
| --- | --- | --- |
| `Assignee::starter()` | | whoever started the instance |
| `Assignee::user($admin)` | `admin:4` (morph alias or class, and key) | that user, of any guard |
| `Assignee::role('hr')` | a role name | anyone with the role when they act |
| `Assignee::permission('approve_leave')` | a permission name | anyone with the permission when they act |
| `Assignee::subject('manager')` | an attribute or relation of the subject | the user (or users) it returns |
| `Assignee::step('review')` | a step key | whoever completed that step last (the starter for the start step) |
| `Assignee::make('yours', $value)` | anything | what your resolver returns, see [Custom assignees](../advanced-usage/custom-assignees.md) |

Users, the starter, subject relations and earlier actors are resolved when the task is created and stored as users. Roles and permissions are stored as they are and checked when someone acts, so people who get the role later can act too.

The [tasks page](pages-and-routes.md) and `WorkflowTask::assignedTo($user)` find tasks by user, by the user's roles (`getRoleNames()`) and by their permissions (`getAllPermissions()`). Nothing assumes `App\Models\User` or the `web` guard.

A task without assignees can only be acted on by someone with `manage_workflow_instances`.
