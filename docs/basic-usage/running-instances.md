---
title: Running instances
sidebar_position: 2
---

An instance is one run of a workflow. Start it, then people take transitions from its tasks until it reaches an end.

# Starting

```php
use Javaabu\Workflows\Facades\Workflows;

$instance = Workflows::start('leave-request', subject: $leave, by: $request->user(), data: $request, comment: 'ޝުކުރިއްޔާ');

// or from the subject (HasWorkflows)
$instance = $leave->startWorkflow('leave-request', by: $request->user(), data: $request);
```

- With a start form, `data` (a request or an array) is validated by it and stored as a form-builder entry; a `ValidationException` means nothing was saved. Without one, an array is the instance's data as it is.
- The subject must be the workflow's subject type (`for()`), and the workflow must be published and active (`is_active`).
- The start step's actions run, it moves on by itself, and the instance stops at its first task, or finishes if no task is on the way.

# Taking a transition

```php
$instance->availableTransitions($manager);   // Transition definitions the manager can take now
$instance->canAct($manager);                 // whether they can act on the current task

$instance->transition('approve', by: $manager, data: $request, comment: 'ރަނގަޅު');
Workflows::transition($instance, 'approve', by: $manager, data: ['approved_days' => 3]);
```

The engine checks, in one database transaction (the instance row is locked):

1. the instance is active and the transition leaves its current task (`WorkflowException` otherwise);
2. the user is one of the task's assignees, or has `manage_workflow_instances` (`AuthorizationException`); pass `force: true` from trusted code to skip this;
3. a required comment is there (`ValidationException` on `comment`);
4. the step's form validates `data`, unless the transition skips the form;
5. the transition's conditions hold with the new data (`ValidationException` on `transition`).

Then it merges the answers into the instance's data, completes the task, runs the transition's actions and enters the next step, moving through automatic steps until it reaches a task or an end. Anything that throws undoes the whole transition.

# Cancelling

```php
$instance->cancel($user, 'ނުބޭނުންވެއްޖެ');
```

Open tasks are cancelled and the reason is kept in the history. Starters can cancel their own instances unless `workflows.starters_can_cancel` is false; `manage_workflow_instances` can cancel any.

# Reading an instance

```php
$instance->status;              // active, completed, cancelled
$instance->outcome;             // the end step's outcome, e.g. approved
$instance->current_step;        // a step key; currentStep() for its definition
$instance->data;                // everything collected; answer('days') for one value
$instance->subject; $instance->starter;
$instance->version;             // the WorkflowVersion it runs on; definition() for its WorkflowDefinition
$instance->currentTask();       // the open WorkflowTask
$instance->tasks;               // every task, in order
$instance->history;             // WorkflowHistory rows, in order

WorkflowInstance::query()->active()->ofWorkflow('leave-request')->forSubject($leave)->get();
$leave->workflowInstances; $leave->workflowInstance('leave-request'); $leave->hasActiveWorkflow('leave-request');
```

# Tasks

A task is one visit to a task step. It has assignees, an optional `due_at`, and once it's done the transition taken, the comment, the form entry (`$task->entry`) and who did it (`$task->completedBy`).

```php
use Javaabu\Workflows\Models\WorkflowTask;

WorkflowTask::query()->open()->assignedTo($user)->get();   // the user's inbox: by name, role or permission
WorkflowTask::query()->overdue()->get();
$task->isAssignedTo($user);
```

# History

Every instance keeps its audit trail in `workflow_history`, one row per event, with the actor (a morph), the step, the previous step, the transition, the action, the comment and data:

| Event | When |
| --- | --- |
| `started` | the instance started (with the start form's answers) |
| `entered` | it reached a step |
| `assigned` | a task was created, with its assignees |
| `transitioned` | it moved along a transition (no actor when it moved by itself) |
| `action_ran`, `action_failed` | an action ran or threw (with the message) |
| `completed` | it reached an end (with the outcome) |
| `cancelled` | it was cancelled (with the reason) |

# Events

All in `Javaabu\Workflows\Events`, dispatched after the database transaction commits:

| Event | Properties |
| --- | --- |
| `WorkflowStarted` | `instance`, `actor` |
| `StepEntered` | `instance`, `step`, `actor` |
| `TaskCreated` | `task` |
| `TransitionTaken` | `instance`, `transition`, `from`, `to`, `actor`, `comment` |
| `WorkflowCompleted` | `instance`, `outcome` |
| `WorkflowCancelled` | `instance`, `actor`, `reason` |
| `ActionFailed` | `instance`, `action`, `exception` (dispatched straight away) |

Use them for anything the package doesn't do, such as escalating overdue tasks or writing to javaabu/activitylog.
