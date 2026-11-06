---
title: The tester
sidebar_position: 8
---

The tester shows the path a run would take with sample data. Nothing is saved and no action runs; actions are described instead.

# On the page

Open a workflow and press *Test*. Fill in:

- **Sample data**: what the start form would collect, as JSON (`{"days": 7}`);
- **Sample subject**: attributes of the record it's about (`{"days": 7, "amount": 1500}`);
- **At each task**: what the assignee chooses; leave one empty to stop there;
- **Test the draft**, when there's one.

The result shows each step in order, the actions that would run, the transition taken and why (*its conditions held*, *no other way matched*, *chosen*), and where it stopped. The path is highlighted on the diagram.

# In code

```php
$run = Workflows::simulate('leave-request',
    data: ['days' => 7],
    subject: ['days' => 7],              // or a model
    choices: [
        'review' => 'approve',
        'hr_review' => ['transition' => 'hr_approve', 'data' => ['note' => 'OK']],
        'revise' => ['resubmit', 'resubmit'],    // one choice per visit
    ],
    draft: true,
);

$run->status;       // completed, waiting (at a task without a choice), stuck, looped
$run->steps();      // ['start', 'review', 'long_leave', 'hr_review', 'approved']
$run->transitions();
$run->outcome;      // approved
$run->options;      // when waiting: the transitions there, and whether they're available
$run->path;         // per step: name, type, actions, transition, reason
$run->toArray();
```

`set_data` applies its values in a simulation, so decisions that depend on it go the right way. Your actions can do the same by overriding `simulate()` (see [Custom actions](../advanced-usage/custom-actions.md)).

Use it in your tests to check a workflow's paths without running them.
