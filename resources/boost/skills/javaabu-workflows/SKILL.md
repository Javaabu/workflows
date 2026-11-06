---
name: javaabu-workflows
description: Build approval and multi-step workflows in a Laravel app with javaabu/workflows. Use when defining workflows in seeders (Workflows::define()->steps([...])->transitions([...])->sync(), Step::start/task/decision/action/end, Transition, Assignee), starting and advancing instances (startWorkflow, Workflows::start, $instance->transition, cancel), assigning tasks (roles, permissions, the subject's relations, the starter, custom assignee types), conditions on transitions, actions (notify, update_model, webhook, set_data, custom Action classes), collecting step data with javaabu/form-builder forms, the workflow tester (Workflows::simulate), the builder and task pages (Route::workflows, Route::workflowTasks) and permissions, events, translatable names, or the laravel/ai workflow assistant.
license: MIT
metadata:
  author: Javaabu
---

# Workflows with javaabu/workflows

The docs ship with the package: `vendor/javaabu/workflows/llms.txt` lists every page in `vendor/javaabu/workflows/docs/`.

| Task | Read |
| --- | --- |
| Define a workflow, run it, the routes | This file |
| Every method, setting, route, event and config key | [references/api.md](references/api.md) |
| Mistakes to avoid | [references/common-mistakes.md](references/common-mistakes.md) |

## 1. Define a workflow in a seeder

```php
use App\Models\LeaveRequest;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;

Workflows::define('leave-request')
    ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
    ->for(LeaveRequest::class)
    ->steps([
        Step::start()->form('leave-request'),
        Step::task('review')
            ->name(['en' => 'Manager review', 'dv' => 'މެނޭޖަރުގެ ރިވިއު'])
            ->assignTo(Assignee::subject('manager'))
            ->dueIn(48)
            ->runs('notify', ['to' => [['type' => 'assignees']], 'title' => 'A leave request to review']),
        Step::decision('long_leave'),
        Step::task('hr_review')->name('HR review')->assignTo(Assignee::role('hr')),
        Step::end('approved')->color('success')->runs('update_model', ['attributes' => ['status' => 'approved']]),
        Step::end('rejected')->color('danger'),
    ])
    ->transitions([
        Transition::make('submit')->from('start')->to('review'),
        Transition::make('approve')->from('review')->to('long_leave')->label('Approve')->color('success'),
        Transition::make('reject')->from('review')->to('rejected')->label('Reject')->color('danger')->requireComment()->skipForm(),
        Transition::make('long')->from('long_leave')->to('hr_review')->when('subject.days', '>', 5),
        Transition::make('short')->from('long_leave')->to('approved'),
        Transition::make('hr_approve')->from('hr_review')->to('approved')->label('Approve'),
    ])
    ->sync();
```

- Step types: `start` (one; moves on by itself), `task` (waits for its assignees), `decision` (picks a transition by conditions), `action` (runs actions, moves on), `end` (finishes with an outcome).
- `sync()` validates (a `ValidationException` lists every problem) and is idempotent: a new version only when the definition changes, published unless `sync(publish: false)`. `->locked()` keeps a workflow to code.
- Keys are lowercase snake_case. Text is a string or a locale map.

## 2. Run it

```php
use Javaabu\Workflows\Concerns\HasWorkflows;   // on LeaveRequest

$instance = $leave->startWorkflow('leave-request', by: $request->user(), data: $request);   // validates the start form

$instance->availableTransitions($manager);        // the buttons the manager has
$instance->transition('approve', by: $manager, data: $request, comment: 'ރަނގަޅު');
$instance->cancel($user, 'reason');

$instance->status; $instance->outcome; $instance->current_step; $instance->answer('days'); $instance->history;
WorkflowTask::query()->open()->assignedTo($user)->get();
```

Transitions throw `AuthorizationException` (not an assignee), `ValidationException` (comment, form, conditions) or `WorkflowException` (wrong step, inactive instance); a failure undoes everything.

## 3. Assignees, conditions, actions

- `Assignee::starter()`, `user($model)`, `role('hr')`, `permission('approve_leave')`, `subject('manager')`, `step('review')`, or your own: `Workflows::assignee('department_head', fn (WorkflowInstance $i) => $i->subject->department->head)`.
- `->when('days', '>', 5)`, `->when('type', ['annual', 'sick'])`, `->when('reason')`, `->whenAny()`. Fields read the data; `subject.` and `starter.` read those models.
- `->runs('notify' | 'update_model' | 'set_data' | 'webhook', $settings)`, with `{{ days }}` placeholders. Custom: extend `Javaabu\Workflows\Actions\Action` (`key()`, `handle(ActionContext)`, `settingsFields()`, `settingsRules()`, `describe()`), register with `Workflows::registerAction()`.

## 4. Routes and permissions

```php
Route::middleware('web')->group(function () {
    Route::workflows(['guard' => 'web_admin', 'prefix' => 'admin/workflows', 'name' => 'admin.workflows']);
    Route::workflowTasks();   // my tasks, start, an instance, transitions
});
```

Run `php artisan permissions:sync`. Permissions: `view_workflows`, `edit_workflows`, `delete_workflows`, `publish_workflows`, `start_workflows`, `view_workflow_instances`, `manage_workflow_instances`. Assignees act without permissions. `workflows.views` is `blade` or `inertia`.

## 5. Test

```php
$run = Workflows::simulate('leave-request', subject: ['days' => 10], choices: ['review' => 'approve', 'hr_review' => 'hr_approve']);
$this->assertSame('approved', $run->outcome);
```

Fake side effects with `Event::fake()`, `Notification::fake()`, `Http::fake()`, `Queue::fake()`. Use Thaana in tests of user-facing behaviour.
