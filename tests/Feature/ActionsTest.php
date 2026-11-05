<?php

namespace Javaabu\Workflows\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Javaabu\Notifications\BaseNotification;
use Javaabu\Permissions\Models\Role;
use Javaabu\Workflows\Actions\ActionContext;
use Javaabu\Workflows\Definitions\ActionCall;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Events\ActionFailed;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Jobs\RunWorkflowAction;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Notifications\PlainWorkflowNotification;
use Javaabu\Workflows\Notifications\WorkflowNotification;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Actions\StampAction;
use Javaabu\Workflows\Tests\TestSupport\Models\LeaveRequest;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StampAction::$runs = [];
        Workflows::registerAction(StampAction::class);
    }

    /**
     * start → review (task, assigned to the subject's manager) → done, with actions
     *
     * @param  array<int, ActionCall>  $on_review
     * @param  array<int, ActionCall>  $on_approve
     */
    protected function workflow(array $on_review = [], array $on_approve = [], array $on_done = []): WorkflowDefinition
    {
        $review = Step::task('review')->assignTo(Assignee::subject('manager'));
        $approve = Transition::make('approve')->from('review')->to('done');
        $done = Step::end('done');

        foreach ($on_review as $call) {
            $review->runs($call);
        }

        foreach ($on_approve as $call) {
            $approve->runs($call);
        }

        foreach ($on_done as $call) {
            $done->runs($call);
        }

        return Workflows::define('actions')->for(LeaveRequest::class)->steps([Step::start(), $review, $done])->transitions([
            Transition::make('go')->from('start')->to('review'),
            $approve,
        ]);
    }

    #[Test]
    public function it_runs_actions_on_steps_and_transitions(): void
    {
        $this->workflow(
            on_review: [ActionCall::make('stamp', ['label' => 'review {{ days }}'])],
            on_approve: [ActionCall::make('stamp', ['label' => 'approved {{ subject.title }}'])],
        )->sync();

        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('actions', subject: $this->leaveRequest(4, $manager), by: $this->makeUser(), data: ['days' => 4]);
        $instance->transition('approve', by: $manager);

        $this->assertSame([
            ['label' => 'review 4', 'step' => 'review', 'transition' => null, 'actor' => $instance->starter_id],
            ['label' => 'approved ހުކުރު ދުވަހުގެ ޗުއްޓީ', 'step' => 'review', 'transition' => 'approve', 'actor' => $manager->getKey()],
        ], StampAction::$runs);

        $this->assertSame(2, $instance->history()->where('event', WorkflowHistory::ACTION_RAN)->where('action', 'stamp')->count());
    }

    #[Test]
    public function the_builder_validates_action_settings(): void
    {
        $this->expectException(ValidationException::class);

        $this->workflow(on_review: [ActionCall::make('stamp')])->sync();
    }

    #[Test]
    public function a_failing_action_is_recorded_and_the_instance_goes_on(): void
    {
        Event::fake([ActionFailed::class]);

        $this->workflow(on_review: [ActionCall::make('stamp', ['label' => 'x', 'fail' => true])])->sync();

        $instance = Workflows::start('actions', subject: $this->leaveRequest(), by: $this->makeUser());

        $this->assertSame('review', $instance->current_step);
        $this->assertSame('The stamp broke.', $instance->history()->where('event', WorkflowHistory::ACTION_FAILED)->first()->data['message']);
        Event::assertDispatched(ActionFailed::class, fn (ActionFailed $event) => $event->action === 'stamp');
    }

    #[Test]
    public function a_failing_action_can_undo_the_transition(): void
    {
        config(['workflows.actions_on_failure' => 'throw']);

        $this->workflow(on_approve: [ActionCall::make('stamp', ['label' => 'x', 'fail' => true])])->sync();

        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('actions', subject: $this->leaveRequest(2, $manager), by: $this->makeUser());

        try {
            $instance->transition('approve', by: $manager);
            $this->fail('The action didn\'t throw.');
        } catch (RuntimeException) {
            $instance->refresh();

            $this->assertSame('review', $instance->current_step);
            $this->assertTrue($instance->isActive());
            $this->assertSame(1, $instance->tasks()->open()->count());
        }
    }

    #[Test]
    public function it_notifies_the_assignees_and_others(): void
    {
        Notification::fake();

        $hr = $this->makeUser('އެޗްއާރު');
        Role::create(['name' => 'hr', 'guard_name' => 'web']);
        $hr->assignRole('hr');

        $this->workflow(on_review: [ActionCall::make('notify', [
            'to' => [['type' => 'assignees'], ['type' => 'role', 'value' => 'hr']],
            'title' => ['en' => 'Leave to review: {{ subject.title }}', 'dv' => 'ރިވިއު ކުރަން: {{ subject.title }}'],
            'message' => 'Days: {{ subject.days }}',
        ])], on_done: [ActionCall::make('notify', ['to' => [['type' => 'starter']], 'title' => 'Approved'])])->sync();

        $manager = $this->makeUser('މެނޭޖަރު');
        $employee = $this->makeUser('އަޙްމަދު');
        $instance = Workflows::start('actions', subject: $this->leaveRequest(6, $manager), by: $employee);

        $class = class_exists(BaseNotification::class) ? WorkflowNotification::class : PlainWorkflowNotification::class;

        Notification::assertSentTo($manager, $class, function ($notification) use ($instance) {
            $this->app->setLocale('dv');

            return $notification->workflow_message->title() === 'ރިވިއު ކުރަން: ހުކުރު ދުވަހުގެ ޗުއްޓީ'
                && $notification->workflow_message->message() === 'Days: 6'
                && $notification->workflow_message->instance->is($instance);
        });
        Notification::assertSentTo($hr, $class);
        Notification::assertNotSentTo($employee, $class);

        $instance->transition('approve', by: $manager);

        Notification::assertSentTo($employee, $class, fn ($notification) => $notification->workflow_message->title() === 'Approved');
    }

    #[Test]
    public function it_sends_a_plain_laravel_notification_without_javaabu_notifications(): void
    {
        config(['workflows.notifications.javaabu' => false]);
        Notification::fake();

        $this->workflow(on_review: [ActionCall::make('notify', ['to' => [['type' => 'starter']], 'title' => 'ސަލާމް', 'channels' => ['database']])])->sync();

        $employee = $this->makeUser();
        Workflows::start('actions', subject: $this->leaveRequest(), by: $employee);

        Notification::assertSentTo($employee, PlainWorkflowNotification::class, function (PlainWorkflowNotification $notification) use ($employee) {
            return $notification->via($employee) === ['database'] && $notification->toArray($employee)['title'] === 'ސަލާމް';
        });
    }

    #[Test]
    public function it_updates_the_subject(): void
    {
        $this->workflow(on_done: [ActionCall::make('update_model', ['attributes' => ['status' => 'approved', 'days' => '{{ approved_days }}']])])->sync();

        $manager = $this->makeUser('މެނޭޖަރު');
        $leave = $this->leaveRequest(5, $manager);
        $instance = Workflows::start('actions', subject: $leave, by: $this->makeUser());
        $instance->transition('approve', by: $manager, data: ['approved_days' => 3]);

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame(3, $leave->days);
    }

    #[Test]
    public function it_sets_data_for_later_decisions(): void
    {
        Workflows::define('data')->steps([
            Step::start()->runs('set_data', ['values' => ['route' => 'fast']]),
            Step::decision('which'),
            Step::end('fast'),
            Step::end('slow'),
        ])->transitions([
            Transition::make('go')->from('start')->to('which'),
            Transition::make('to_fast')->from('which')->to('fast')->when('route', 'fast'),
            Transition::make('to_slow')->from('which')->to('slow'),
        ])->sync();

        $instance = Workflows::start('data');

        $this->assertSame('fast', $instance->outcome);
        $this->assertSame('fast', $instance->answer('route'));
    }

    #[Test]
    public function it_calls_a_signed_webhook_from_the_queue(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response(['ok' => true])]);

        $this->workflow(on_approve: [ActionCall::make('webhook', ['url' => 'https://hooks.example.com/leave', 'secret' => 'shh', 'headers' => ['X-App' => 'hr']])])->sync();

        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('actions', subject: $this->leaveRequest(2, $manager), by: $this->makeUser(), data: ['reason' => 'ބަލި']);
        $instance->transition('approve', by: $manager);

        Http::assertSent(function (Request $request) use ($instance) {
            $body = $request->body();

            return $request->url() === 'https://hooks.example.com/leave'
                && $request->method() === 'POST'
                && $request->header('X-App') === ['hr']
                && $request->header('X-Workflows-Signature') === ['sha256=' . hash_hmac('sha256', $body, 'shh')]
                && $request['instance'] === $instance->getKey()
                && $request['transition'] === 'approve'
                && $request['data'] === ['reason' => 'ބަލި'];
        });
    }

    #[Test]
    public function queued_actions_wait_for_the_transition_to_be_saved(): void
    {
        Queue::fake();

        $this->workflow(on_approve: [ActionCall::make('webhook', ['url' => 'https://hooks.example.com/leave'])])->sync();

        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('actions', subject: $this->leaveRequest(2, $manager), by: $this->makeUser());
        $instance->transition('approve', by: $manager);

        Queue::assertPushed(RunWorkflowAction::class, fn (RunWorkflowAction $job) => $job->instance_id === $instance->getKey() && $job->transition === 'approve' && $job->actor_id === $manager->getKey());
    }

    #[Test]
    public function actions_describe_themselves(): void
    {
        $instance = new WorkflowInstance(['data' => ['days' => 4]]);

        $context = fn (string $action, array $settings) => new ActionContext($instance, ActionCall::make($action, $settings), simulated: true);

        $this->assertSame('Call POST https://example.com/4', Workflows::action('webhook')->describe($context('webhook', ['url' => 'https://example.com/{{ days }}'])));
        $this->assertSame('Set status = approved on the subject', Workflows::action('update_model')->describe($context('update_model', ['attributes' => ['status' => 'approved']])));
        $this->assertSame('Send "Hi" to starter, role hr', Workflows::action('notify')->describe($context('notify', ['to' => [['type' => 'starter'], ['type' => 'role', 'value' => 'hr']], 'title' => 'Hi'])));
    }
}
