---
title: Defining workflows
sidebar_position: 1
---

A workflow is a set of **steps** joined by **transitions**. Define it in code (a seeder) or in the [builder](the-builder.md); both save the same definition.

# Steps

| Type | Made with | What it does |
| --- | --- | --- |
| `start` | `Step::start($key = 'start')` | Where an instance begins. Its form (if any) is filled by whoever starts it. It moves on by itself. Exactly one per workflow. |
| `task` | `Step::task('review')` | Waits for its assignees; one of them takes one of its transitions. Can have a form. |
| `decision` | `Step::decision('long_leave')` | Moves on by itself, along the first transition whose [conditions](conditions.md) hold, or the one without conditions. |
| `action` | `Step::action('notify_hr')` | Runs its [actions](actions.md), then moves on like a decision. |
| `end` | `Step::end('approved', $outcome = 'approved')` | Finishes the instance with an outcome. At least one per workflow. |

Every step takes:

```php
Step::task('review')
    ->name(['en' => 'Manager review', 'dv' => 'މެނޭޖަރުގެ ރިވިއު'])  // text, or a map of locale => text
    ->description('Check the dates')
    ->assignTo(Assignee::subject('manager'), Assignee::role('hr'))  // tasks only, see Assignees
    ->form('leave-review')            // start and tasks: a form-builder form, see Step data
    ->form('leave-review', 2)         // ...pinned to version 2
    ->runs('notify', ['to' => [['type' => 'assignees']], 'title' => 'A leave request to review'])
    ->dueIn(48)                       // tasks: due 48 hours after they're created
    ->color('warning')                // primary, secondary, success, info, warning, danger
    ->position(240, 120);             // where the builder draws it
```

Keys are lowercase letters, numbers and underscores, starting with a letter, and unique among the steps.

# Transitions

```php
Transition::make('approve')
    ->from('review')->to('long_leave')
    ->label(['en' => 'Approve', 'dv' => 'ގަބޫލުކުރޭ'])   // the button on a task
    ->color('success')
    ->when('days', '>', 5)            // conditions, see Conditions
    ->comment('required')             // none, optional (default) or required; requireComment()
    ->skipForm()                      // take it without filling the step's form, e.g. Reject
    ->runs('update_model', ['attributes' => ['status' => 'approved']]);
```

From a task, transitions are buttons in the order you define them. From an automatic step (start, decision, action), the ones with conditions are tried first, in order, then the one without.

# The whole definition

```php
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;

Workflows::define('leave-request')
    ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
    ->description('Leave goes to the manager; long leave also to HR.')
    ->for(LeaveRequest::class)        // the model instances are about (optional)
    ->steps([
        Step::start()->form('leave-request'),
        Step::task('review')->name('Manager review')->assignTo(Assignee::subject('manager'))->dueIn(48),
        Step::decision('long_leave')->name('Long leave?'),
        Step::task('hr_review')->name('HR review')->assignTo(Assignee::role('hr')),
        Step::task('revise')->name('Revise')->assignTo(Assignee::starter()),
        Step::end('approved')->name('Approved')->color('success')
            ->runs('update_model', ['attributes' => ['status' => 'approved']])
            ->runs('notify', ['to' => [['type' => 'starter']], 'title' => 'Your leave is approved']),
        Step::end('rejected')->name('Rejected')->color('danger'),
    ])
    ->transitions([
        Transition::make('submit')->from('start')->to('review'),
        Transition::make('approve')->from('review')->to('long_leave')->label('Approve')->color('success'),
        Transition::make('send_back')->from('review')->to('revise')->label('Send back')->requireComment()->skipForm(),
        Transition::make('reject')->from('review')->to('rejected')->label('Reject')->color('danger')->requireComment()->skipForm(),
        Transition::make('resubmit')->from('revise')->to('review')->label('Resubmit'),
        Transition::make('long')->from('long_leave')->to('hr_review')->when('subject.days', '>', 5),
        Transition::make('short')->from('long_leave')->to('approved'),
        Transition::make('hr_approve')->from('hr_review')->to('approved')->label('Approve')->color('success'),
        Transition::make('hr_reject')->from('hr_review')->to('rejected')->label('Reject')->color('danger'),
    ])
    ->sync();
```

# Syncing and seeding

`sync()` validates the definition (it throws a `ValidationException` listing every problem) and saves it:

- a new key creates the workflow;
- a changed definition gets a new version, published unless you pass `sync(publish: false)` or set `workflows.sync.publish` to false;
- the same definition again changes nothing, so seeders can run on every deploy;
- a soft-deleted workflow is restored.

```php
class WorkflowsSeeder extends Seeder
{
    public function run(): void
    {
        LeaveRequestWorkflow::definition()->locked()->sync();
    }
}
```

`locked()` keeps the workflow to code: the builder shows it read-only, and `$workflow->draft()` throws.

# Versions and drafts

A workflow has at most one draft, which the builder edits. Publishing freezes it: published versions can't change, and a version with instances can't be deleted. Instances run on the version they started with, so changing a workflow never changes a run in progress.

```php
$workflow = Workflows::workflow('leave-request');
$workflow->publishedVersion;        // WorkflowVersion
$workflow->draft($user);            // the draft, made from the published version when there's none
$workflow->publish($user);          // validates, then publishes the draft
$workflow->discardDraft();
$workflow->definition();            // the published WorkflowDefinition; definition(draft: true) for the draft
```

# What's checked

The validator (`Workflows::validator()->errors($definition)`) reports, keyed by `steps.<key>`, `transitions.<key>` or `definition`:

- exactly one start step, at least one end step;
- unique, well-formed keys; known step types, assignee types and actions; valid action settings;
- forms that exist, and pinned versions that are published;
- transitions that start and end at steps, nothing leaving an end, nothing leading into the start;
- a way out of every step but an end, and a path from every step to an end;
- at most one transition without conditions from an automatic step;
- condition fields and operators.

`unreachable($definition)` lists steps no path reaches; the builder warns about them, but they don't block publishing.
