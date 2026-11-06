<?php

namespace Javaabu\Workflows\Tests\Http;

use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class BuilderTest extends TestCase
{
    use InteractsWithWorkflows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
    }

    #[Test]
    public function it_lists_and_searches_workflows(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $this->actingAs($this->editor())
            ->get('/workflows')
            ->assertOk()
            ->assertSee('Leave request')
            ->assertSee('/workflows/create', false);

        $this->actingAs($this->editor())->get('/workflows?search=nothing')->assertSee('No workflows match your search.');

        $this->actingAs($this->editor())->getJson('/workflows')->assertJsonPath('data.0.key', 'leave-request')->assertJsonPath('data.0.status', 'published');
    }

    #[Test]
    public function it_loads_the_styles_on_every_blade_page_and_the_builder_only_where_it_mounts(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $this->actingAs($this->editor())->get('/workflows')->assertOk()
            ->assertSee('data-manifest-asset="workflows::styles"', false)
            ->assertDontSee('data-manifest-asset="workflows::builder"', false);

        $this->actingAs($this->editor())->get('/workflows/leave-request')->assertOk()
            ->assertSee('data-manifest-asset="workflows::styles"', false)
            ->assertSee('data-manifest-asset="workflows::builder"', false);
    }

    #[Test]
    public function it_creates_a_workflow_with_a_starter_draft(): void
    {
        $this->actingAs($this->editor())->get('/workflows/create')->assertOk()->assertSee('name="key"', false);

        $this->actingAs($this->editor())
            ->post('/workflows', ['key' => 'purchase', 'name' => 'ގަތުން', 'is_active' => '1'])
            ->assertRedirect('/workflows/purchase')
            ->assertSessionHas('status', 'Workflow created.');

        $workflow = Workflows::workflow('purchase');

        $this->assertSame('ގަތުން', $workflow->name);
        $this->assertFalse($workflow->isPublished());
        $this->assertSame(['start', 'review', 'done'], array_map(fn ($step) => $step->getKey(), $workflow->definition(draft: true)->getSteps()));

        $this->actingAs($this->editor())->post('/workflows', ['key' => 'purchase', 'name' => 'Again'])->assertSessionHasErrors('key');
        $this->actingAs($this->editor())->post('/workflows', ['key' => 'Bad Key!', 'name' => 'x'])->assertSessionHasErrors('key');
    }

    #[Test]
    public function it_shows_the_builder_with_its_catalog(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $response = $this->actingAs($this->editor())->get('/workflows/leave-request')->assertOk();

        $response->assertSee('data-workflow-builder', false)
            ->assertSee('Manager review')
            ->assertSee('/workflows/leave-request/definition', false);

        $this->actingAs($this->editor())->getJson('/workflows/leave-request')
            ->assertJsonPath('builder.definition.steps.1.key', 'review')
            ->assertJsonPath('builder.catalog.actions.0.key', 'notify')
            ->assertJsonPath('builder.errors', [])
            ->assertJsonPath('builder.editable', true);
    }

    #[Test]
    public function it_saves_drafts_with_their_problems_and_publishes_them(): void
    {
        $workflow = LeaveRequestWorkflow::definition()->sync();
        $definition = $workflow->definition()->structure();

        // Half built: a step with nowhere to go
        $definition['steps'][] = ['key' => 'orphan', 'type' => 'task', 'name' => 'ޔަތީމު', 'position' => ['x' => 10, 'y' => 10]];

        $this->actingAs($this->editor())
            ->putJson('/workflows/leave-request/definition', ['definition' => $definition])
            ->assertOk()
            ->assertJsonPath('message', 'Draft saved.')
            ->assertJsonPath('unreachable', ['orphan'])
            ->assertJsonStructure(['errors' => ['steps.orphan']]);

        $this->assertTrue($workflow->refresh()->hasDraft());

        $this->actingAs($this->editor())->postJson('/workflows/leave-request/publish')->assertStatus(422);

        array_pop($definition['steps']);
        $definition['steps'][1]['name'] = 'ރިވިއު';

        $this->actingAs($this->editor())->putJson('/workflows/leave-request/definition', ['definition' => json_encode($definition)])->assertJsonPath('errors', []);

        $this->actingAs($this->editor())
            ->post('/workflows/leave-request/publish')
            ->assertRedirect('/workflows/leave-request')
            ->assertSessionHas('status', 'Version 2 published.');

        $this->assertSame('ރިވިއު', $workflow->refresh()->definition()->getStep('review')->getName());

        $this->actingAs($this->editor())->putJson('/workflows/leave-request/definition', ['definition' => ['nope' => true]])->assertJsonValidationErrors('definition');
    }

    #[Test]
    public function it_discards_a_draft(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        LeaveRequestWorkflow::definition()->step(Step::end('x'))->sync(publish: false);

        $this->actingAs($this->editor())->delete('/workflows/leave-request/draft')->assertRedirect('/workflows/leave-request');

        $this->assertFalse(Workflows::workflow('leave-request')->hasDraft());
    }

    #[Test]
    public function it_updates_settings_and_deletes(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $this->actingAs($this->editor())->get('/workflows/leave-request/edit')->assertOk()->assertSee('Leave request');

        $this->actingAs($this->editor())
            ->patch('/workflows/leave-request', ['key' => 'leave-request', 'name' => 'ޗުއްޓީ', 'subject_type' => 'leave_request'])
            ->assertRedirect('/workflows/leave-request');

        $workflow = Workflows::workflow('leave-request');
        $this->assertSame('ޗުއްޓީ', $workflow->name);
        $this->assertFalse($workflow->is_active);

        $this->actingAs($this->editor())->delete('/workflows/leave-request')->assertRedirect('/workflows');
        $this->assertSoftDeleted($workflow);
    }

    #[Test]
    public function it_lists_versions_and_exports(): void
    {
        LeaveRequestWorkflow::definition()->sync();
        LeaveRequestWorkflow::definition()->step(Step::end('x'))->transition(Transition::make('to_x')->from('review')->to('x'))->sync();

        $this->actingAs($this->editor())->getJson('/workflows/leave-request/versions')
            ->assertJsonPath('data.0.number', 2)
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.number', 1);

        $this->actingAs($this->editor())->get('/workflows/leave-request/versions')->assertOk()->assertSee('data-version="2"', false);

        $export = $this->actingAs($this->editor())->get('/workflows/leave-request/export');
        $export->assertOk()->assertDownload('leave-request.json');

        $json = json_decode($export->streamedContent(), true);
        $this->assertSame('leave-request', $json['key']);
        $this->assertSame(1, $json['workflows']);
        $this->assertSame('ޗުއްޓީގެ އެދުން', $json['name']['dv'] ?? 'ޗުއްޓީގެ އެދުން');
    }

    #[Test]
    public function the_tester_shows_the_path(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $this->actingAs($this->editor())->get('/workflows/leave-request/test')->assertOk()->assertSee('choices[review]', false);

        $this->actingAs($this->editor())
            ->post('/workflows/leave-request/test', ['subject' => '{"days": 9}', 'choices' => ['review' => 'approve', 'hr_review' => 'hr_approve']])
            ->assertOk()
            ->assertSee('data-simulation-status="completed"', false)
            ->assertSee('data-path-step="hr_review"', false);

        $this->actingAs($this->editor())
            ->postJson('/workflows/leave-request/test', ['data' => ['days' => 1], 'choices' => ['review' => 'reject']])
            ->assertJsonPath('simulation.status', 'completed')
            ->assertJsonPath('simulation.outcome', 'rejected');

        $this->actingAs($this->editor())->post('/workflows/leave-request/test', ['data' => '{oops'])->assertSessionHasErrors('data');
    }

    #[Test]
    public function locked_workflows_are_read_only(): void
    {
        LeaveRequestWorkflow::definition()->locked()->sync();

        $this->actingAs($this->editor())->get('/workflows/leave-request')->assertOk()->assertSee('This workflow is read-only here.');
        $this->actingAs($this->editor())->putJson('/workflows/leave-request/definition', ['definition' => ['steps' => []]])->assertForbidden();
        $this->actingAs($this->editor())->delete('/workflows/leave-request')->assertForbidden();
        $this->actingAs($this->editor())->getJson('/workflows/leave-request')->assertJsonPath('builder.editable', false);
    }

    #[Test]
    public function it_works_for_another_guard(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        // A user of the default guard isn't signed in on the admin guard
        $this->actingAs($this->editor())->getJson('/admin/workflows')->assertUnauthorized();

        $this->actingAs($this->adminEditor(), 'web_admin')
            ->get('/admin/workflows/leave-request')
            ->assertOk()
            ->assertSee('/admin/workflows/leave-request/definition', false);

        $this->actingAs($this->adminEditor(), 'web_admin')
            ->post('/admin/workflows', ['key' => 'onboarding', 'name' => 'Onboarding'])
            ->assertRedirect('/admin/workflows/onboarding');

        $this->assertInstanceOf(Workflow::class, Workflows::workflow('onboarding'));

    }
}
