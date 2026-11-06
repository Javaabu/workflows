---
title: Introduction
sidebar_position: 1.0
---

# Workflows

`javaabu/workflows` runs dynamic workflows in Laravel apps: leave requests, purchase approvals, applications, anything that goes through steps where people decide what happens next.

- **Definitions** have steps (start, task, decision, action, end), transitions between them, assignees and conditions. They're versioned: drafts are edited, published versions never change, and every instance stays on the version it started with.
- **Instances** move through the steps. Tasks wait for their assignees (a user, a role, a permission, someone related to the record, whoever did an earlier step, or your own rule). Everything that happens is kept in the instance's history, and events fire for each step.
- **Fluent definitions** for seeders: `Workflows::define('leave-request')->steps([...])->transitions([...])->sync()`, idempotent and lockable to code.
- **A visual builder**: drag steps, connect them, and edit them in an inspector, all by keyboard too. It's a React component, rendered by the Inertia page and mounted on the Blade page.
- **Actions** run when a step starts or a transition is taken: send a notification (javaabu/notifications when installed), update the record, call a webhook, set data, or your own.
- **Conditions** on transitions choose the way through decisions, and which buttons people see.
- **Step data** is collected with [javaabu/form-builder](https://github.com/Javaabu/form-builder) forms, validated and stored with each task.
- **A tester** shows the path a run takes with sample data, without saving anything.
- **An optional AI assistant** (laravel/ai) designs a workflow, or changes one, from a description.
- Pages in Blade and Inertia React, overridable controllers, permissions through [javaabu/permissions](https://github.com/Javaabu/permissions), optional [javaabu/translatable](https://github.com/Javaabu/translatable), Dhivehi and right to left.

```php
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;

Workflows::define('leave-request')
    ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
    ->for(LeaveRequest::class)
    ->steps([
        Step::start()->form('leave-request'),
        Step::task('review')->name('Manager review')->assignTo(Assignee::subject('manager')),
        Step::end('approved'),
        Step::end('rejected'),
    ])
    ->transitions([
        Transition::make('submit')->from('start')->to('review'),
        Transition::make('approve')->from('review')->to('approved')->label('Approve')->color('success'),
        Transition::make('reject')->from('review')->to('rejected')->label('Reject')->color('danger')->requireComment()->skipForm(),
    ])
    ->sync();

$instance = $leave->startWorkflow('leave-request', by: $request->user(), data: $request);
$instance->transition('approve', by: $manager, comment: 'ރަނގަޅު');
```

Start with [Installation & Setup](installation-and-setup.md), then [Defining workflows](basic-usage/defining-workflows.md).
