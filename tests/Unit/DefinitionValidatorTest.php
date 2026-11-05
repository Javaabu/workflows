<?php

namespace Javaabu\Workflows\Tests\Unit;

use Javaabu\Workflows\Definitions\DefinitionValidator;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Tests\TestCase;
use Javaabu\Workflows\Tests\TestSupport\Workflows\LeaveRequestWorkflow;
use PHPUnit\Framework\Attributes\Test;

class DefinitionValidatorTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, array<int, string>>
     */
    protected function errors(array $definition): array
    {
        return app(DefinitionValidator::class)->errors($definition);
    }

    #[Test]
    public function a_good_definition_has_no_errors(): void
    {
        $this->assertSame([], app(DefinitionValidator::class)->errors(LeaveRequestWorkflow::definition()));
    }

    #[Test]
    public function it_needs_one_start_and_an_end(): void
    {
        $this->assertArrayHasKey('definition', $this->errors(['steps' => []]));

        $errors = $this->errors(['steps' => [['key' => 'a', 'type' => 'task']], 'transitions' => []]);
        $this->assertContains('A workflow has exactly one start step.', $errors['definition']);
        $this->assertContains('A workflow needs at least one end step.', $errors['definition']);
    }

    #[Test]
    public function it_checks_keys_types_and_transitions(): void
    {
        $errors = $this->errors([
            'steps' => [
                ['key' => 'start', 'type' => 'start'],
                ['key' => 'Bad Key', 'type' => 'task'],
                ['key' => 'odd', 'type' => 'party'],
                ['key' => 'done', 'type' => 'end'],
                ['key' => 'done', 'type' => 'end'],
            ],
            'transitions' => [
                ['key' => 'go', 'from' => 'start', 'to' => 'nowhere'],
                ['key' => 'back', 'from' => 'done', 'to' => 'start'],
            ],
        ]);

        $this->assertArrayHasKey('steps.Bad Key', $errors);
        $this->assertSame(['"party" isn\'t a step type.'], $errors['steps.odd']);
        $this->assertSame(['Two steps have the key done.'], $errors['steps.done']);
        $this->assertSame(['The transition go doesn\'t lead to a step.'], $errors['transitions.go']);
        $this->assertSame(['Nothing leaves an end step.', 'Nothing leads back to the start step.'], $errors['transitions.back']);
    }

    #[Test]
    public function every_step_needs_a_way_out_and_to_an_end(): void
    {
        $errors = $this->errors([
            'steps' => [
                ['key' => 'start', 'type' => 'start'],
                ['key' => 'a', 'type' => 'task'],
                ['key' => 'b', 'type' => 'task'],
                ['key' => 'done', 'type' => 'end'],
            ],
            'transitions' => [
                ['key' => 'go', 'from' => 'start', 'to' => 'a'],
                ['key' => 'ab', 'from' => 'a', 'to' => 'b'],
                ['key' => 'ba', 'from' => 'b', 'to' => 'a'],
            ],
        ]);

        $this->assertSame(['No path from start reaches an end step.'], $errors['steps.start']);
        $this->assertSame(['No path from a reaches an end step.'], $errors['steps.a']);
    }

    #[Test]
    public function automatic_steps_have_one_default(): void
    {
        $errors = $this->errors([
            'steps' => [
                ['key' => 'start', 'type' => 'start'],
                ['key' => 'a', 'type' => 'end'],
                ['key' => 'b', 'type' => 'end'],
            ],
            'transitions' => [
                ['key' => 'one', 'from' => 'start', 'to' => 'a'],
                ['key' => 'two', 'from' => 'start', 'to' => 'b'],
            ],
        ]);

        $this->assertArrayHasKey('steps.start', $errors);
    }

    #[Test]
    public function it_checks_assignees_actions_forms_and_conditions(): void
    {
        $errors = $this->errors([
            'steps' => [
                ['key' => 'start', 'type' => 'start', 'form' => 'missing-form'],
                ['key' => 'review', 'type' => 'task', 'assignees' => [['type' => 'role'], ['type' => 'wizard', 'value' => 'x'], ['type' => 'user', 'value' => '4']], 'actions' => [['action' => 'teleport']]],
                ['key' => 'done', 'type' => 'end', 'assignees' => [['type' => 'starter']]],
            ],
            'transitions' => [
                ['key' => 'go', 'from' => 'start', 'to' => 'review'],
                ['key' => 'ok', 'from' => 'review', 'to' => 'done', 'conditions' => ['match' => 'all', 'rules' => [['field' => 'Bad Field!', 'operator' => '='], ['field' => 'type', 'operator' => 'in', 'value' => 'x']]]],
            ],
        ]);

        $this->assertSame(['There\'s no form with the key missing-form.'], $errors['steps.start']);
        $this->assertSame([
            'Choose who the task is assigned to.',
            '"wizard" isn\'t an assignee type.',
            'A user assignee is the user\'s type and key, like admin:4.',
            '"teleport" isn\'t an action.',
        ], $errors['steps.review']);
        $this->assertSame(['Only tasks have assignees.'], $errors['steps.done']);
        $this->assertSame(['"Bad Field!" isn\'t a field a condition can check.', '"in" needs a list of values.'], $errors['transitions.ok']);
    }

    #[Test]
    public function it_finds_unreachable_steps(): void
    {
        $definition = LeaveRequestWorkflow::definition();
        $definition->step(Step::end('orphan'));

        $this->assertSame(['orphan'], app(DefinitionValidator::class)->unreachable($definition));
    }

    #[Test]
    public function definitions_round_trip_through_arrays(): void
    {
        $definition = LeaveRequestWorkflow::definition();

        $this->assertEquals($definition->toArray(), WorkflowDefinition::fromArray($definition->toArray())->toArray());
        $this->assertSame('leave_request', $definition->getSubject());
        $this->assertSame(['long', 'short'], array_map(fn ($transition) => $transition->getKey(), $definition->transitionsFrom('long_leave')));
    }
}
