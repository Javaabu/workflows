<?php

namespace Javaabu\Workflows\Tests\Http;

use Javaabu\Workflows\Ai\WorkflowAssistant;
use Javaabu\Workflows\Ai\WorkflowAssistantAgent;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use Laravel\Ai\Promptable;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\Attributes\Test;

#[RequiresMethod(Promptable::class, 'fake')]
class AssistantTest extends TestCase
{
    use InteractsWithWorkflows;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWorkflows();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $steps
     * @return array<string, mixed>
     */
    protected function purchase(?array $steps = null): array
    {
        return [
            'workflows' => 1,
            'key' => 'purchase',
            'name' => 'ގަތުމުގެ އެދުން',
            'steps' => $steps ?? [
                ['key' => 'start', 'type' => 'start', 'name' => 'Requested'],
                ['key' => 'approval', 'type' => 'task', 'name' => 'Finance approval', 'assignees' => [['type' => 'starter']]],
                ['key' => 'approved', 'type' => 'end', 'name' => 'Approved', 'outcome' => 'approved'],
            ],
            'transitions' => [
                ['key' => 'submit', 'from' => 'start', 'to' => 'approval'],
                ['key' => 'approve', 'from' => 'approval', 'to' => 'approved', 'label' => 'ގަބޫލު', 'color' => 'success'],
            ],
        ];
    }

    #[Test]
    public function it_is_offered_with_laravel_ai(): void
    {
        $this->assertTrue(WorkflowAssistant::available());

        $this->actingAs($this->editor())->get('/workflows')->assertSee('/workflows/assistant', false);

        config(['workflows.assistant.enabled' => false]);
        $this->assertFalse(WorkflowAssistant::available());
    }

    #[Test]
    public function it_builds_a_draft_from_a_description(): void
    {
        WorkflowAssistantAgent::fake([
            ['summary' => 'A purchase approval by finance.', 'workflow' => json_encode($this->purchase())],
        ]);

        $this->actingAs($this->editor())
            ->post('/workflows/assistant', ['description' => 'Purchases are approved by finance'])
            ->assertRedirect('/workflows/purchase')
            ->assertSessionHas('status', 'A purchase approval by finance.');

        $workflow = Workflows::workflow('purchase');

        $this->assertFalse($workflow->isPublished());
        $this->assertSame('ގަތުމުގެ އެދުން', $workflow->name);
        $definition = $workflow->definition(draft: true);
        $this->assertSame(['start', 'approval', 'approved'], array_map(fn ($step) => $step->getKey(), $definition->getSteps()));
        $this->assertNotNull($definition->getStep('approval')->getPosition());

        WorkflowAssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Purchases are approved by finance'));
    }

    #[Test]
    public function it_retries_with_the_errors_and_gives_up(): void
    {
        $broken = $this->purchase([['key' => 'lonely', 'type' => 'task']]);

        WorkflowAssistantAgent::fake([
            ['summary' => 'x', 'workflow' => json_encode($broken)],
            ['summary' => 'Fixed.', 'workflow' => json_encode($this->purchase())],
        ]);

        $this->actingAs($this->editor())->post('/workflows/assistant', ['description' => 'A purchase approval'])->assertRedirect('/workflows/purchase');

        WorkflowAssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Your last answer had these errors') && str_contains($prompt->prompt, 'exactly one start step'));

        WorkflowAssistantAgent::fake([
            ['summary' => 'x', 'workflow' => 'not json'],
            ['summary' => 'x', 'workflow' => json_encode($broken)],
        ]);

        $this->actingAs($this->editor())
            ->post('/workflows/assistant', ['description' => 'Another one'])
            ->assertSessionHasErrors('description');
    }

    #[Test]
    public function it_changes_a_workflows_draft(): void
    {
        LeaveRequestWorkflow::definition()->sync();

        $changed = LeaveRequestWorkflow::definition()->toArray();
        $changed['steps'][1]['due_in'] = 24;

        WorkflowAssistantAgent::fake([['summary' => 'The review is due in a day.', 'workflow' => json_encode($changed)]]);

        $this->actingAs($this->editor())
            ->postJson('/workflows/leave-request/assistant', ['description' => 'Make the review due in 24 hours'])
            ->assertOk()
            ->assertJsonPath('message', 'The review is due in a day.')
            ->assertJsonPath('builder.definition.steps.1.due_in', 24);

        $workflow = Workflows::workflow('leave-request');
        $this->assertSame(48, $workflow->definition()->getStep('review')->getDueIn());
        $this->assertSame(24, $workflow->definition(draft: true)->getStep('review')->getDueIn());

        WorkflowAssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'The workflow now:') && str_contains($prompt->prompt, '"key":"review"'));
    }

    #[Test]
    public function it_knows_the_catalog(): void
    {
        $instructions = app(WorkflowAssistant::class)->instructions();

        $this->assertStringContainsString('notify (Send a notification)', $instructions);
        $this->assertStringContainsString('webhook', $instructions);
        $this->assertStringContainsString('Step types: start', $instructions);
    }

    #[Test]
    public function it_needs_permission(): void
    {
        $this->actingAs($this->editor([WorkflowPolicy::VIEW]))->post('/workflows/assistant', ['description' => 'x'])->assertForbidden();
    }
}
