---
title: Testing
sidebar_position: 12
---

# A workflow's paths

Use the [tester](../basic-usage/the-tester.md) to check every way through a workflow without running it:

```php
public function test_long_leave_goes_to_hr(): void
{
    LeaveRequestWorkflow::definition()->sync();

    $run = Workflows::simulate('leave-request', subject: ['days' => 10], choices: ['review' => 'approve', 'hr_review' => 'hr_approve']);

    $this->assertSame(['start', 'review', 'long_leave', 'hr_review', 'approved'], $run->steps());
}
```

# Real runs

```php
$manager = User::factory()->create();
$leave = LeaveRequest::factory()->for($manager, 'manager')->create(['days' => 2]);

$instance = $leave->startWorkflow('leave-request', by: $employee);
$this->assertTrue($instance->canAct($manager));

$instance->transition('approve', by: $manager, comment: 'ރަނގަޅު');

$this->assertSame('approved', $instance->outcome);
$this->assertSame('approved', $leave->refresh()->status);
```

Validate a definition before you ship it: `$this->assertSame([], Workflows::validator()->errors(LeaveRequestWorkflow::definition()))`.

# Side effects

- `Event::fake()` and `Event::assertDispatched(TransitionTaken::class, fn ($event) => $event->transition === 'approve')`. The events dispatch after the transaction commits, which tests handle.
- `Notification::fake()` and `Notification::assertSentTo($manager, WorkflowNotification::class)` (`PlainWorkflowNotification` without javaabu/notifications); `$notification->workflow_message->title()` is the text.
- `Http::fake()` for webhooks; `Queue::fake()` and `Queue::assertPushed(RunWorkflowAction::class)` to check queued actions without running them.
- `config(['workflows.actions_on_failure' => 'throw'])` to see an action's exception in a test.

# Pages

The routes answer JSON, so `getJson('/tasks/1')` returns the page's data, and with `workflows.views` = `inertia`, `assertInertia(fn ($page) => $page->component('workflows::Instances/Show'))`.
