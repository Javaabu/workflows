---
title: Custom assignees
sidebar_position: 3
---

Add an assignee type when tasks go to someone your app works out: the department head, the on-call officer, the island council.

```php
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowInstance;

// a service provider's boot()
Workflows::assignee('department_head', function (WorkflowInstance $instance, ?string $value) {
    return $instance->subject->department->head;
}, __('Department head'));
```

The resolver gets the instance and the assignee's value, and returns a user, several users, or `Assignee` definitions to resolve in turn (`Assignee::role('council_'.$instance->subject->island_id)`). An invokable class name works too.

Use it in code or pick it in the builder:

```php
Step::task('approval')->assignTo(Assignee::make('department_head'));
```

It's resolved when the task is created, like the built-in user types.
