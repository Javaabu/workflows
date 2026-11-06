<?php

namespace Javaabu\Workflows\Tests\Http;

use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Forms\LeaveForms;
use Javaabu\Workflows\Tests\TestSupport\Models\LeaveRequest;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class TasksTest extends TestCase
{
    use InteractsWithWorkflows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
    }

    /**
     * A workflow about nothing in particular, with a start form and a review form
     */
    protected function suggestions(): void
    {
        LeaveForms::request();
        LeaveForms::review();

        Workflows::define('suggestion')
            ->name('ހިޔާލު')
            ->steps([
                Step::start()->form('leave-request'),
                Step::task('review')->name('Review')->assignTo(Assignee::permission(WorkflowPolicy::VIEW))->form('leave-review'),
                Step::end('accepted')->name('Accepted'),
                Step::end('declined')->name('Declined'),
            ])
            ->transitions([
                Transition::make('submit')->from('start')->to('review'),
                Transition::make('accept')->from('review')->to('accepted')->label('ގަބޫލު')->color('success'),
                Transition::make('decline')->from('review')->to('declined')->label('Decline')->color('danger')->skipForm()->requireComment(),
            ])
            ->sync();
    }

    #[Test]
    public function people_start_workflows_from_their_tasks_page(): void
    {
        $this->suggestions();
        $employee = $this->makeUser('އަޙްމަދު', [WorkflowPolicy::START]);

        $this->actingAs($employee)->get('/tasks')->assertOk()
            ->assertSee('Nothing is waiting for you.')
            ->assertSee('ހިޔާލު')
            ->assertSee('/tasks/start/suggestion', false);

        $this->actingAs($employee)->get('/tasks/start/suggestion')->assertOk()->assertSee('name="leave_type"', false);

        $this->actingAs($employee)->post('/tasks/start/suggestion', ['days' => 2])->assertSessionHasErrors('leave_type');

        $response = $this->actingAs($employee)->post('/tasks/start/suggestion', ['leave_type' => 'annual', 'days' => 2, 'reason' => 'ދަތުރު', 'comment' => 'ޝުކުރިއްޔާ']);

        $instance = WorkflowInstance::first();
        $response->assertRedirect("/tasks/{$instance->getKey()}")->assertSessionHas('status', 'Started ހިޔާލު.');

        $this->assertSame('review', $instance->current_step);
        $this->assertSame('ދަތުރު', $instance->answer('reason'));

        $this->actingAs($employee)->get('/tasks?view=started')->assertSee("data-instance-id=\"{$instance->getKey()}\"", false);
    }

    #[Test]
    public function assignees_see_their_tasks_and_act_with_the_form(): void
    {
        $this->suggestions();
        $instance = Workflows::start('suggestion', by: $this->makeUser(), data: ['leave_type' => 'sick', 'days' => 1]);
        $reviewer = $this->makeUser('ރިވިއުކުރާ', [WorkflowPolicy::VIEW]);

        $this->actingAs($reviewer)->get('/tasks')->assertOk()->assertSee("/tasks/{$instance->getKey()}", false);

        $this->actingAs($reviewer)->get("/tasks/{$instance->getKey()}")->assertOk()
            ->assertSee('Your turn')
            ->assertSee('name="approved_days"', false)
            ->assertSee('value="accept"', false)
            ->assertSee('formnovalidate', false)
            ->assertSee('data-workflows-entry="start"', false);

        $this->actingAs($reviewer)
            ->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'accept'])
            ->assertSessionHasErrors('approved_days');

        $this->actingAs($reviewer)
            ->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'decline'])
            ->assertSessionHasErrors('comment');

        $this->actingAs($reviewer)
            ->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'accept', 'approved_days' => 1, 'note' => 'ރަނގަޅު', 'comment' => 'ބާއްޖަވެރި'])
            ->assertRedirect("/tasks/{$instance->getKey()}")
            ->assertSessionHas('status', 'Done: ގަބޫލު.');

        $instance->refresh();
        $this->assertSame('accepted', $instance->outcome);

        $this->actingAs($reviewer)->get("/tasks/{$instance->getKey()}")->assertOk()
            ->assertDontSee('Your turn')
            ->assertSee('ބާއްޖަވެރި')
            ->assertSee('data-workflows-entry="review"', false)
            ->assertSee('ރަނގަޅު');

        $this->actingAs($reviewer)->getJson("/tasks/{$instance->getKey()}")
            ->assertJsonPath('instance.status', 'completed')
            ->assertJsonPath('history.0.event', 'started')
            ->assertJsonPath('can.act', false);
    }

    #[Test]
    public function others_cant_see_or_act(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        $manager = $this->makeUser('މެނޭޖަރު');
        $instance = Workflows::start('leave-request', subject: $this->leaveRequest(2, $manager), by: $this->makeUser());
        $stranger = $this->makeUser('ބޭރުމީހާ');

        $this->actingAs($stranger)->get("/tasks/{$instance->getKey()}")->assertForbidden();
        $this->actingAs($stranger)->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'approve'])->assertForbidden();
        $this->actingAs($stranger)->get('/tasks/999')->assertNotFound();

        $this->actingAs($manager)->post("/tasks/{$instance->getKey()}/transition", ['transition' => 'approve'])->assertRedirect();
        $this->assertTrue($instance->refresh()->isCompleted());
    }

    #[Test]
    public function starters_and_managers_cancel(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        $employee = $this->makeUser();
        $instance = Workflows::start('leave-request', subject: $this->leaveRequest(), by: $employee);

        $this->actingAs($employee)->get("/tasks/{$instance->getKey()}")->assertSee('Cancel this');

        $this->actingAs($employee)
            ->post("/tasks/{$instance->getKey()}/cancel", ['reason' => 'ގޯހެއް'])
            ->assertRedirect("/tasks/{$instance->getKey()}");

        $this->assertTrue($instance->refresh()->isCancelled());

        config(['workflows.starters_can_cancel' => false]);
        $other = Workflows::start('leave-request', subject: $this->leaveRequest(), by: $employee);
        $this->actingAs($employee)->post("/tasks/{$other->getKey()}/cancel")->assertForbidden();
        $this->actingAs($this->editor())->post("/tasks/{$other->getKey()}/cancel")->assertRedirect();
    }

    #[Test]
    public function workflows_about_a_subject_start_from_it(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        $employee = $this->makeUser('އަޙްމަދު', [WorkflowPolicy::START]);
        $leave = $this->leaveRequest();

        // Not offered on the tasks page: it needs a leave request
        $this->actingAs($employee)->get('/tasks')->assertDontSee('/tasks/start/leave-request', false);
        $this->actingAs($employee)->get('/tasks/start/leave-request')->assertNotFound();

        $this->actingAs($employee)->get("/tasks/start/leave-request?subject={$leave->getKey()}")->assertOk()->assertSee($leave->title);
        $this->actingAs($employee)->post("/tasks/start/leave-request?subject={$leave->getKey()}")->assertRedirect();

        $this->assertTrue($leave->hasActiveWorkflow('leave-request'));
        $this->assertInstanceOf(LeaveRequest::class, WorkflowInstance::first()->subject);
    }

    #[Test]
    public function starting_needs_the_permission(): void
    {
        $this->suggestions();

        $this->actingAs($this->makeUser())->get('/tasks/start/suggestion')->assertForbidden();
        $this->actingAs($this->makeUser())->get('/tasks')->assertDontSee('/tasks/start/suggestion', false);
    }

    #[Test]
    public function admins_see_a_workflows_instances(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        $instance = Workflows::start('leave-request', subject: $this->leaveRequest(), by: $this->makeUser());

        $this->actingAs($this->editor())->get('/workflows/leave-request/instances')->assertOk()
            ->assertSee("/workflows/leave-request/instances/{$instance->getKey()}", false)
            ->assertSee('ހުކުރު ދުވަހުގެ ޗުއްޓީ');

        $this->actingAs($this->editor())->get('/workflows/leave-request/instances?status=completed')->assertSee('There are no instances yet.');

        $this->actingAs($this->editor())->get("/workflows/leave-request/instances/{$instance->getKey()}")->assertOk()->assertSee('History');

        // An editor with manage_workflow_instances can act from the admin page
        $this->actingAs($this->editor())
            ->post("/workflows/leave-request/instances/{$instance->getKey()}/transition", ['transition' => 'approve'])
            ->assertRedirect("/workflows/leave-request/instances/{$instance->getKey()}");

        $this->actingAs($this->makeUser())->get('/workflows/leave-request/instances')->assertForbidden();
    }

    #[Test]
    public function admins_on_another_guard_get_their_tasks(): void
    {
        $admin = $this->makeAdmin();

        Workflows::define('approval')->steps([
            Step::start(),
            Step::task('check')->assignTo(Assignee::user($admin)),
            Step::end('done'),
        ])->transitions([
            Transition::make('go')->from('start')->to('check'),
            Transition::make('ok')->from('check')->to('done')->label('OK'),
        ])->sync();

        $instance = Workflows::start('approval', by: $this->makeUser());

        $this->actingAs($admin, 'web_admin')->get('/admin/tasks')->assertOk()->assertSee("/admin/tasks/{$instance->getKey()}", false);
        $this->actingAs($admin, 'web_admin')->post("/admin/tasks/{$instance->getKey()}/transition", ['transition' => 'ok'])->assertRedirect();

        $this->assertTrue($instance->refresh()->isCompleted());
        $this->assertSame('admin', $instance->history()->where('transition', 'ok')->first()->actor_type);
    }

    #[Test]
    public function notifications_link_to_the_instance_page(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        $instance = Workflows::start('leave-request', subject: $this->leaveRequest(), by: $this->makeUser());

        $this->assertSame(url("/tasks/{$instance->getKey()}"), Workflows::instanceUrl($instance));

        Workflows::instanceUrlUsing(fn (WorkflowInstance $instance) => "https://app.test/leave/{$instance->getKey()}");
        $this->assertSame("https://app.test/leave/{$instance->getKey()}", Workflows::instanceUrl($instance));
        Workflows::instanceUrlUsing(null);
    }
}
