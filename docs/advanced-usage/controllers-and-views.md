---
title: Controllers and views
sidebar_position: 5
---

# Controllers

Every page's controller can be replaced with a subclass, for every route group from config, or for one group:

```php
// config/workflows.php
'controllers' => [
    'workflows' => App\Http\Controllers\WorkflowsController::class,
],

// one group
Route::workflowTasks(['controllers' => ['instances' => App\Http\Controllers\LeaveInstancesController::class]]);
```

| Key | Class | Pages |
| --- | --- | --- |
| `workflows` | `WorkflowsController` | list, create, settings, builder, save, publish, discard, versions, export, delete |
| `tester` | `TesterController` | the tester |
| `instances` | `InstancesController` | a workflow's instances, an instance, transitions, cancelling |
| `tasks` | `TasksController` | my tasks |
| `start` | `StartController` | starting a workflow |
| `assistant` | `AssistantController` | the AI assistant |

Useful hooks: `InstancesController::query()` scopes the admin instance list, `StartController::subject()` finds and authorizes the subject.

```php
class LeaveInstancesController extends InstancesController
{
    protected function query(Request $request, Workflow $workflow)
    {
        return parent::query($request, $workflow)->whereHasMorph('subject', [LeaveRequest::class], fn ($query) => $query->where('department_id', $request->user()->department_id));
    }
}
```

# Views

Blade pages are `workflows::workflows.index`, `workflows.edit`, `workflows.show`, `workflows.versions`, `workflows.test`, `instances.index`, `instances.show`, `tasks.index` and `start.show`, with partials in `workflows::partials`. Publish them to change them:

```bash
php artisan vendor:publish --tag=workflows-views
```

With `workflows.views` = `inertia`, the same pages render as `workflows::Workflows/Index`, `workflows::Workflows/Edit`, `workflows::Workflows/Show`, `workflows::Workflows/Versions`, `workflows::Workflows/Test`, `workflows::Instances/Index`, `workflows::Instances/Show`, `workflows::Tasks/Index` and `workflows::Start/Show`, with the same props (their types are in `@javaabu/workflows`). Override a React page with `php artisan manifest:override workflows js/Pages/Instances/Show.tsx`.

Both stacks get the same `strings`, so they read the same in every language.
