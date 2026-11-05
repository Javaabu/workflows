<?php

namespace Javaabu\Workflows\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Javaabu\Permissions\Models\Role;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Events\StepEntered;
use Javaabu\Workflows\Events\TaskCreated;
use Javaabu\Workflows\Events\TransitionTaken;
use Javaabu\Workflows\Events\WorkflowCancelled;
use Javaabu\Workflows\Events\WorkflowCompleted;
use Javaabu\Workflows\Events\WorkflowStarted;
use Javaabu\Workflows\Exceptions\WorkflowException;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Models\User;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class EngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permissions:sync');

        LeaveRequestWorkflow::definition()->sync();
    }

    #[Test]
    public function it_starts_an_instance_and_assigns_the_first_task(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $employee = $this->makeUser('އަޙްމަދު');
        $leave = $this->leaveRequest(3, $manager);

        $instance = $leave->startWorkflow('leave-request', by: $employee, data: ['reason' => 'ބަލި']);

        $this->assertTrue($instance->isActive());
        $this->assertSame('review', $instance->current_step);
        $this->assertSame('ބަލި', $instance->answer('reason'));
        $this->assertTrue($instance->starter->is($employee));
        $this->assertTrue($instance->subject->is($leave));

        $task = $instance->currentTask();
        $this->assertSame('review', $task->step);
        $this->assertTrue($task->isAssignedTo($manager));
        $this->assertFalse($task->isAssignedTo($employee));
        $this->assertNotNull($task->due_at);
        $this->assertSame([$task->getKey()], WorkflowTask::assignedTo($manager)->open()->pluck('id')->all());

        $this->assertSame(
            ['started', 'entered', 'transitioned', 'entered', 'assigned'],
            $instance->history->pluck('event')->all(),
        );
        $this->assertTrue($leave->hasActiveWorkflow('leave-request'));
        $this->assertTrue($leave->workflowInstance('leave-request')->is($instance));
    }

    #[Test]
    public function it_takes_transitions_through_decisions_to_an_end(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $this->makeUser());

        $this->assertSame(['approve', 'send_back', 'reject'], array_map(fn ($transition) => $transition->getKey(), $instance->availableTransitions($manager)));

        $instance->transition('approve', by: $manager, comment: 'ރަނގަޅު');

        $this->assertTrue($instance->isCompleted());
        $this->assertSame('approved', $instance->outcome);
        $this->assertSame('approved', $instance->current_step);
        $this->assertNotNull($instance->completed_at);

        $task = $instance->tasks()->first();
        $this->assertSame(WorkflowTask::COMPLETED, $task->status);
        $this->assertSame('approve', $task->transition);
        $this->assertSame('ރަނގަޅު', $task->comment);
        $this->assertTrue($task->completedBy->is($manager));

        $approved = $instance->history()->where('event', WorkflowHistory::TRANSITIONED)->where('transition', 'approve')->first();
        $this->assertTrue($approved->actor->is($manager));
        $this->assertSame('ރަނގަޅު', $approved->comment);

        // The decision moved on by itself, without an actor
        $short = $instance->history()->where('transition', 'short')->first();
        $this->assertNull($short->actor_id);
    }

    #[Test]
    public function it_follows_a_decisions_conditions(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $hr = $this->makeUser('އެޗްއާރު');
        Role::create(['name' => 'hr', 'guard_name' => 'web']);
        $hr->assignRole('hr');

        $instance = $this->leaveRequest(10, $manager)->startWorkflow('leave-request', by: $this->makeUser());
        $instance->transition('approve', by: $manager);

        $this->assertSame('hr_review', $instance->current_step);
        $this->assertTrue($instance->canAct($hr));
        $this->assertFalse($instance->canAct($manager));
        $this->assertSame(1, WorkflowTask::assignedTo($hr)->open()->count());

        $instance->transition('hr_approve', by: $hr);

        $this->assertSame('approved', $instance->outcome);
    }

    #[Test]
    public function it_sends_a_task_back_to_the_starter(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $employee = $this->makeUser('އަޙްމަދު');
        $instance = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $employee);

        $instance->transition('send_back', by: $manager, comment: 'ތާރީޚު ބަދަލުކުރައްވާ');

        $this->assertSame('revise', $instance->current_step);
        $this->assertTrue($instance->canAct($employee));

        $instance->transition('resubmit', by: $employee);

        $this->assertSame('review', $instance->current_step);
        $this->assertSame(3, $instance->tasks()->count());
    }

    #[Test]
    public function it_only_lets_assignees_act(): void
    {
        $instance = $this->leaveRequest(2)->startWorkflow('leave-request', by: $this->makeUser());
        $stranger = $this->makeUser('ބޭރުމީހާ');

        $this->assertSame([], $instance->availableTransitions($stranger));

        try {
            $instance->transition('approve', by: $stranger);
            $this->fail('A stranger took a transition.');
        } catch (AuthorizationException) {
            $this->assertSame('review', $instance->refresh()->current_step);
        }

        $manager_of_all = $this->makeUser('އިދާރާ', [WorkflowPolicy::MANAGE_INSTANCES]);
        $instance->transition('approve', by: $manager_of_all);

        $this->assertTrue($instance->isCompleted());
    }

    #[Test]
    public function it_asks_for_required_comments(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $this->makeUser());

        try {
            $instance->transition('reject', by: $manager, comment: '  ');
            $this->fail('Rejected without a comment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('comment', $exception->errors());
        }

        $instance->transition('reject', by: $manager, comment: 'ޗުއްޓީ ނުލިބޭނެ');

        $this->assertSame('rejected', $instance->outcome);
    }

    #[Test]
    public function it_refuses_transitions_from_other_steps(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $this->makeUser());

        $this->expectException(WorkflowException::class);

        $instance->transition('hr_approve', by: $manager);
    }

    #[Test]
    public function it_cancels_an_instance(): void
    {
        $employee = $this->makeUser();
        $instance = $this->leaveRequest(2)->startWorkflow('leave-request', by: $employee);

        $instance->cancel($employee, 'ނުބޭނުންވެއްޖެ');

        $this->assertTrue($instance->isCancelled());
        $this->assertSame(0, $instance->tasks()->open()->count());
        $this->assertSame('ނުބޭނުންވެއްޖެ', $instance->history()->where('event', WorkflowHistory::CANCELLED)->value('comment'));

        $this->expectException(WorkflowException::class);
        $instance->transition('approve', force: true);
    }

    #[Test]
    public function it_fires_events(): void
    {
        Event::fake();

        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $this->makeUser());
        $instance->transition('approve', by: $manager);

        Event::assertDispatched(WorkflowStarted::class);
        Event::assertDispatched(TaskCreated::class, fn (TaskCreated $event) => $event->task->step === 'review');
        Event::assertDispatched(StepEntered::class, 4);
        Event::assertDispatched(TransitionTaken::class, fn (TransitionTaken $event) => $event->transition === 'approve' && $event->actor->is($manager));
        Event::assertDispatched(WorkflowCompleted::class, fn (WorkflowCompleted $event) => $event->outcome === 'approved');
        Event::assertNotDispatched(WorkflowCancelled::class);
    }

    #[Test]
    public function instances_stay_on_their_version(): void
    {
        $manager = $this->makeUser('މެނޭޖަރު');
        $old = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $this->makeUser());

        // v2 drops the decision: approve goes straight to approved
        $definition = LeaveRequestWorkflow::definition();
        $definition->transitions(array_values(array_filter($definition->getTransitions(), fn ($transition) => ! in_array($transition->getKey(), ['approve'], true))));
        $definition->transition(Transition::make('approve')->from('review')->to('rejected')->label('Approve'));
        $definition->sync();

        $this->assertSame(2, Workflows::workflow('leave-request')->publishedVersion->number);

        $old->transition('approve', by: $manager);
        $this->assertSame('approved', $old->outcome);
        $this->assertSame(1, $old->version->number);

        $new = $this->leaveRequest(2, $manager)->startWorkflow('leave-request', by: $this->makeUser());
        $new->transition('approve', by: $manager);
        $this->assertSame('rejected', $new->outcome);
    }

    #[Test]
    public function it_needs_a_published_workflow_and_the_right_subject(): void
    {
        $this->expectException(WorkflowException::class);

        Workflows::start('leave-request', subject: $this->makeUser(), by: $this->makeUser());
    }

    #[Test]
    public function it_stops_loops_of_automatic_steps(): void
    {
        config(['workflows.max_automatic_steps' => 5]);

        Workflows::define('loop')->steps([
            Step::start(),
            Step::decision('a'),
            Step::decision('b'),
            Step::end('done'),
        ])->transitions([
            Transition::make('go')->from('start')->to('a'),
            Transition::make('ab')->from('a')->to('b'),
            Transition::make('ba')->from('b')->to('a')->when('never_set', 'blank'),
            Transition::make('end')->from('b')->to('done'),
        ])->sync();

        try {
            Workflows::start('loop');
            $this->fail('The loop ran forever.');
        } catch (WorkflowException $exception) {
            $this->assertStringContainsString('more than 5 automatic steps', $exception->getMessage());
            $this->assertSame(0, WorkflowInstance::count());
        }
    }

    #[Test]
    public function a_user_on_another_guard_can_be_assigned(): void
    {
        $admin = $this->makeAdmin();

        Workflows::define('approval')->steps([
            Step::start(),
            Step::task('check')->assignTo(Assignee::user($admin)),
            Step::end('done'),
        ])->transitions([
            Transition::make('go')->from('start')->to('check'),
            Transition::make('ok')->from('check')->to('done'),
        ])->sync();

        $instance = Workflows::start('approval', by: $this->makeUser());

        $this->assertTrue($instance->canAct($admin));
        $this->assertFalse($instance->canAct(User::first()));

        $instance->transition('ok', by: $admin);
        $this->assertTrue($instance->isCompleted());
    }
}
