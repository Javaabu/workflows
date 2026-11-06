---
title: Pages and routes
sidebar_position: 9
---

Two macros register the pages. Call them inside your `web` group, once per guard that needs them.

```php
Route::workflows(['guard' => 'web_admin', 'prefix' => 'admin/workflows', 'name' => 'admin.workflows']);
Route::workflowTasks();                                                                        // /tasks, the default guard
Route::workflowTasks(['guard' => 'web_admin', 'prefix' => 'admin/tasks', 'name' => 'admin.workflow-tasks']);
```

| Option | Default | |
| --- | --- | --- |
| `guard` | `null` (the default guard) | the guard users sign in with; authorization uses it |
| `prefix` | `workflows` / `tasks` | the URL prefix |
| `name` | `workflows` / `workflow-tasks` | the route name prefix |
| `middleware` | `auth` or `auth:{guard}` | replaces the default |
| `controllers` | `config('workflows.controllers')` | per-group controllers, see [Controllers and views](../advanced-usage/controllers-and-views.md) |

# Route::workflows()

| Method | URL | Name | Page |
| --- | --- | --- | --- |
| GET | `/` | `index` | the workflows, with search |
| GET, POST | `/create`, `/` | `create`, `store` | a new workflow (key, subject, name, description, active) |
| GET | `/{workflow}` | `show` | the [builder](the-builder.md) |
| GET, PATCH, DELETE | `/{workflow}/edit`, `/{workflow}` | `edit`, `update`, `destroy` | settings, deleting |
| PUT | `/{workflow}/definition` | `definition` | saves the draft (JSON) |
| POST | `/{workflow}/publish` | `publish` | publishes the draft |
| DELETE | `/{workflow}/draft` | `draft.destroy` | discards the draft |
| GET | `/{workflow}/versions` | `versions` | every version, who published it, its instances |
| GET | `/{workflow}/export` | `export` | the definition as JSON (`?draft=1` for the draft) |
| GET, POST | `/{workflow}/test` | `test`, `test.run` | the [tester](the-tester.md) |
| GET | `/{workflow}/instances` | `instances.index` | the instances, by status |
| GET | `/{workflow}/instances/{instance}` | `instances.show` | an instance |
| POST | `/{workflow}/instances/{instance}/transition`, `/cancel` | `instances.transition`, `instances.cancel` | acting on it |
| POST | `/assistant`, `/{workflow}/assistant` | `assistant.create`, `assistant` | the [AI assistant](../advanced-usage/ai-assistant.md), with laravel/ai |

# Route::workflowTasks()

| Method | URL | Name | Page |
| --- | --- | --- | --- |
| GET | `/` | `index` | *Assigned to me* (open tasks, overdue first) and `?view=started` (*Started by me*), and the workflows the user can start |
| GET, POST | `/start/{workflow}` | `start`, `start.store` | the start form; `?subject=<key>` for workflows about a model |
| GET | `/{instance}` | `instances.show` | an instance: where it is, what it collected, its history, and the user's task with its form and buttons |
| POST | `/{instance}/transition` | `instances.transition` | `transition`, `comment` and the form's fields |
| POST | `/{instance}/cancel` | `instances.cancel` | `reason` |

Link to a start page from a record's page:

```blade
<x-forms::link-button :url="route('workflow-tasks.start', ['leave-request', 'subject' => $leave->id])">{{ __('Request approval') }}</x-forms::link-button>
```

When the subject has a policy, the user needs its `view` ability to start a workflow about it.

Notifications link to the first `Route::workflowTasks()`'s instance page; change that with `workflows.instance_route` or `Workflows::instanceUrlUsing(fn (WorkflowInstance $instance) => route('leave.show', $instance->subject))`.

# JSON

Every route answers JSON when the request expects it: the same data the pages get, `{message, …}` after an action, and Laravel's 422 validation errors.
