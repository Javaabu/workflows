---
title: Installation & Setup
sidebar_position: 1.2
---

# Installing

Until the next-gen packages are released, add their VCS repositories to your `composer.json` (see this package's `composer.json` for the list), then:

```bash
composer require javaabu/workflows
php artisan migrate
php artisan permissions:sync
```

The migration creates `workflows`, `workflow_versions`, `workflow_instances`, `workflow_tasks`, `workflow_task_assignees` and `workflow_history`. To run it yourself, publish it and call `WorkflowsManager::ignoreMigrations()` in a service provider's `register()`:

```bash
php artisan vendor:publish --tag=workflows-migrations
```

`permissions:sync` creates the package's [permissions](basic-usage/permissions.md) for every guard javaabu/permissions knows.

# Routes

Nothing is registered until you ask. Add the admin pages (workflows, the builder, the tester, instances) and the people's pages (their tasks, starting workflows, acting on instances) inside your `web` group:

```php
// routes/web.php
Route::middleware('web')->group(function () {
    Route::workflows(['guard' => 'web_admin', 'prefix' => 'admin/workflows', 'name' => 'admin.workflows']);
    Route::workflowTasks();   // /tasks for the default guard
});
```

Both take `guard`, `prefix`, `name`, `middleware` and `controllers`. See [Pages and routes](basic-usage/pages-and-routes.md).

# Subjects

A workflow can be about a model (a leave request, an application). Add `HasWorkflows` to it:

```php
use Javaabu\Workflows\Concerns\HasWorkflows;

class LeaveRequest extends Model
{
    use HasWorkflows;
}
```

Users who are assigned tasks need no trait: they're morphs, for any guard and model. To send them notifications, they use Laravel's (or javaabu/notifications') `Notifiable`.

# Views

The pages render with Blade by default. For Inertia React:

```dotenv
WORKFLOWS_VIEWS=inertia
```

Install `inertiajs/inertia-laravel`, add manifest's Vite plugin, and resolve the package's pages (`workflows::Workflows/Index`, …) with manifest's page resolver. Blade pages extend `config('workflows.layout')` (`layouts.app`) and fill its `content` section.

# Configuration

The config works without publishing. To change it:

```bash
php artisan vendor:publish --tag=workflows-config
```

Every option is in [Configuration](advanced-usage/configuration.md). Views and translations publish with `--tag=workflows-views` and `--tag=workflows-translations`.

# A first workflow

```php
// database/seeders/WorkflowsSeeder.php
Workflows::define('leave-request')
    ->name('Leave request')
    ->for(LeaveRequest::class)
    ->steps([...])
    ->transitions([...])
    ->sync();
```

Then start it from your code (`$leave->startWorkflow('leave-request', by: $user)`) or from the tasks page. See [Defining workflows](basic-usage/defining-workflows.md) and [Running instances](basic-usage/running-instances.md).
