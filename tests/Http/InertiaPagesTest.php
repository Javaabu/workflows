<?php

namespace Javaabu\Workflows\Tests\Http;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Forms\LeaveForms;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\Test;

/**
 * The Inertia pages and their props. With WORKFLOWS_UPDATE_FIXTURES=1 it
 * writes the props to tests/js/fixtures, which the React page tests render.
 */
#[RequiresMethod(InertiaServiceProvider::class, 'register')]
class InertiaPagesTest extends TestCase
{
    use InteractsWithWorkflows;

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InertiaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('workflows.views', 'inertia');
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
        $this->travelTo('2026-11-06 10:00:00');
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function writeFixture(string $name, array $props): void
    {
        unset($props['errors']);

        if (getenv('WORKFLOWS_UPDATE_FIXTURES')) {
            file_put_contents(
                __DIR__ . "/../js/fixtures/{$name}.json",
                json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            );
        }
    }

    /**
     * A workflow with forms, about nothing in particular
     */
    protected function suggestions(): void
    {
        LeaveForms::request();
        LeaveForms::review();

        Workflows::define('suggestion')
            ->name('Suggestion')
            ->steps([
                Step::start()->name('Submitted')->form('leave-request')->position(40, 120),
                Step::task('review')->name('Review')->assignTo(Assignee::permission(WorkflowPolicy::VIEW))->form('leave-review')->dueIn(24)->position(300, 120),
                Step::end('accepted')->name('Accepted')->color('success')->position(560, 40),
                Step::end('declined')->name('Declined')->color('danger')->position(560, 200),
            ])
            ->transitions([
                Transition::make('submit')->from('start')->to('review'),
                Transition::make('accept')->from('review')->to('accepted')->label('ގަބޫލު')->color('success'),
                Transition::make('decline')->from('review')->to('declined')->label('Decline')->color('danger')->skipForm()->requireComment(),
            ])
            ->sync();
    }

    #[Test]
    public function it_renders_the_workflows_list(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $response = $this->actingAs($this->editor())->get('/workflows')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('workflows::Workflows/Index')
                ->where('workflows.0.key', 'leave-request')
                ->where('can.create', true)
                ->etc());

        $this->writeFixture('workflows-index', $response->viewData('page')['props']);
    }

    #[Test]
    public function it_renders_the_builder(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $response = $this->actingAs($this->editor())->get('/workflows/leave-request')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('workflows::Workflows/Show')
                ->where('builder.definition.steps.1.key', 'review')
                ->where('urls.save', 'http://localhost/workflows/leave-request/definition')
                ->where('can.update', true)
                ->etc());

        $this->writeFixture('workflows-show', $response->viewData('page')['props']);
    }

    #[Test]
    public function it_renders_the_settings_and_versions(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $this->actingAs($this->editor())->get('/workflows/create')->assertInertia(fn (AssertableInertia $page) => $page->component('workflows::Workflows/Edit')->has('form.components')->etc());

        $response = $this->actingAs($this->editor())->get('/workflows/leave-request/edit')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows::Workflows/Edit')->where('record.key', 'leave-request')->etc());
        $this->writeFixture('workflows-edit', $response->viewData('page')['props']);

        $response = $this->actingAs($this->editor())->get('/workflows/leave-request/versions')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows::Workflows/Versions')->where('versions.0.number', 1)->etc());
        $this->writeFixture('workflows-versions', $response->viewData('page')['props']);
    }

    #[Test]
    public function it_renders_the_tester(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $response = $this->actingAs($this->editor())->post('/workflows/leave-request/test', ['subject' => '{"days": 9}', 'choices' => ['review' => 'approve']])->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('workflows::Workflows/Test')
                ->where('simulation.status', 'waiting')
                ->where('tasks.0.key', 'review')
                ->etc());

        $this->writeFixture('workflows-test', $response->viewData('page')['props']);
    }

    #[Test]
    public function it_renders_an_instance_with_its_task(): void
    {
        $this->suggestions();
        $instance = Workflows::start('suggestion', by: $this->makeUser('އަޙްމަދު'), data: ['leave_type' => 'sick', 'days' => 2, 'reason' => 'ބަލި']);
        $reviewer = $this->makeUser('ރިވިއުކުރާ', [WorkflowPolicy::VIEW]);

        $response = $this->actingAs($reviewer)->get("/tasks/{$instance->getKey()}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('workflows::Instances/Show')
                ->where('can.act', true)
                ->where('transitions.0.key', 'accept')
                ->where('transitions.1.skips_form', true)
                ->has('form.components')
                ->has('entries', 1)
                ->etc());

        $this->writeFixture('instances-show', $response->viewData('page')['props']);

        $response = $this->actingAs($reviewer)->get('/tasks')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows::Tasks/Index')->where('items.0.id', $instance->getKey())->etc());

        $this->writeFixture('tasks-index', $response->viewData('page')['props']);

        $response = $this->actingAs($this->editor())->get('/workflows/suggestion/instances')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows::Instances/Index')->where('instances.0.id', $instance->getKey())->etc());

        $this->writeFixture('instances-index', $response->viewData('page')['props']);
    }

    #[Test]
    public function it_renders_the_start_page(): void
    {
        $this->suggestions();

        $response = $this->actingAs($this->makeUser('އަޙްމަދު', [WorkflowPolicy::START]))->get('/tasks/start/suggestion')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('workflows::Start/Show')->has('form.components')->where('urls.store', 'http://localhost/tasks/start/suggestion')->etc());

        $this->writeFixture('start-show', $response->viewData('page')['props']);
    }
}
