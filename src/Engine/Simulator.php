<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Actions\ActionContext;
use Javaabu\Workflows\Definitions\ActionCall;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowVersion;
use Javaabu\Workflows\Support\SampleModel;
use Throwable;

/**
 * The workflow tester: walks a definition with sample data, without saving
 * anything or running actions. Automatic steps choose as they would for real;
 * tasks take the transition given in $choices for their step, or stop there.
 *
 * ```php
 * $run = Workflows::simulate('leave-request', data: ['days' => 7], subject: ['days' => 7], choices: [
 *     'review' => 'approve',                                         // a transition
 *     'hr_review' => ['transition' => 'hr_approve', 'data' => [...]], // with the task's form data
 *     'revise' => ['resubmit', 'resubmit'],                          // one per visit
 * ]);
 * $run->status;  $run->steps();  $run->outcome;
 * ```
 */
class Simulator
{
    public function __construct(
        protected ConditionEvaluator $conditions,
        protected Engine $engine,
    ) {}

    /**
     * @param  array<string, mixed>  $data  what the start step's form would collect
     * @param  array<string, mixed>  $choices  step key => transition key, {transition, data}, or a list of those (one per visit)
     * @param  Model|array<string, mixed>|null  $subject  the subject, or sample attributes of one
     * @param  Model|array<string, mixed>|null  $starter
     */
    public function simulate(
        Workflow|WorkflowVersion|WorkflowDefinition|string $workflow,
        array $data = [],
        array $choices = [],
        Model|array|null $subject = null,
        Model|array|null $starter = null,
        bool $draft = false,
        ?int $limit = null,
    ): Simulation {
        $definition = $this->definition($workflow, $draft);
        $limit ??= (int) config('workflows.max_automatic_steps', 100);

        $instance = new WorkflowInstance(['data' => $data, 'status' => WorkflowInstance::ACTIVE]);
        $instance->setRelation('subject', is_array($subject) ? SampleModel::from($subject) : $subject);
        $instance->setRelation('starter', is_array($starter) ? SampleModel::from($starter) : $starter);

        $visits = [];
        $path = [];
        $step = $definition->getStartStep();

        if (! $step) {
            return new Simulation(Simulation::STUCK, [], $data, message: __('A workflow has exactly one start step.'));
        }

        for ($count = 0; $count <= $limit; $count++) {
            $entry = [
                'step' => $step->getKey(),
                'type' => $step->getType(),
                'name' => Workflows::text($step->getName()) ?? $step->getKey(),
                'actions' => $this->describe($step->getActions(), $instance, $step->getKey()),
            ];

            if ($step->isEnd()) {
                $path[] = $entry;

                return new Simulation(Simulation::COMPLETED, $path, $instance->data ?? [], outcome: $step->getOutcome() ?? $step->getKey());
            }

            $scope = $this->scope($instance);

            if ($step->isTask()) {
                $choice = $this->choice($choices, $step->getKey(), $visits[$step->getKey()] ?? 0);
                $visits[$step->getKey()] = ($visits[$step->getKey()] ?? 0) + 1;

                $options = $this->options($definition, $step, $scope);

                if ($choice === null) {
                    $path[] = [...$entry, 'options' => $options];

                    return new Simulation(Simulation::WAITING, $path, $instance->data ?? [], message: __('Waiting at :step: choose what happens there.', ['step' => $entry['name']]), options: $options);
                }

                $instance->data = [...($instance->data ?? []), ...$choice['data']];
                $transition = $definition->getTransition($choice['transition']);

                if (! $transition || $transition->getFrom() !== $step->getKey() || ! $this->conditions->passes($transition->getConditions(), $this->scope($instance))) {
                    $path[] = [...$entry, 'options' => $options];

                    return new Simulation(Simulation::STUCK, $path, $instance->data ?? [], message: __(':transition can\'t be taken from :step.', ['transition' => $choice['transition'], 'step' => $entry['name']]), options: $options);
                }

                $reason = 'chosen';
            } else {
                $transition = $this->engine->pick($scope, $definition, $step);

                if (! $transition) {
                    $path[] = $entry;

                    return new Simulation(Simulation::STUCK, $path, $instance->data ?? [], message: __('No transition out of :step matches the data.', ['step' => $entry['name']]));
                }

                $reason = $transition->hasConditions() ? 'conditions' : 'default';
            }

            $path[] = [
                ...$entry,
                'transition' => $transition->getKey(),
                'transition_label' => Workflows::text($transition->getLabel()),
                'reason' => $reason,
                'transition_actions' => $this->describe($transition->getActions(), $instance, $step->getKey(), $transition->getKey()),
            ];

            $step = $definition->getStep((string) $transition->getTo());

            if (! $step) {
                return new Simulation(Simulation::STUCK, $path, $instance->data ?? [], message: __('The transition :key doesn\'t lead to a step.', ['key' => $transition->getKey()]));
            }
        }

        return new Simulation(Simulation::LOOPED, $path, $instance->data ?? [], message: __('The run went through more than :count steps. Check the workflow for loops.', ['count' => $limit]));
    }

    protected function definition(Workflow|WorkflowVersion|WorkflowDefinition|string $workflow, bool $draft): WorkflowDefinition
    {
        if ($workflow instanceof WorkflowDefinition) {
            return $workflow;
        }

        if ($workflow instanceof WorkflowVersion) {
            return $workflow->definition();
        }

        $workflow = $workflow instanceof Workflow ? $workflow : Workflows::workflow($workflow);

        return $workflow->definition($draft) ?? WorkflowDefinition::make($workflow->key);
    }

    /**
     * The choice for the nth visit to a step: {transition, data}
     *
     * @param  array<string, mixed>  $choices
     * @return array{transition: string, data: array<string, mixed>}|null
     */
    protected function choice(array $choices, string $step, int $visit): ?array
    {
        $choice = $choices[$step] ?? null;

        if (is_array($choice) && array_is_list($choice)) {
            $choice = $choice[$visit] ?? null;
        }

        if (is_string($choice) && $choice !== '') {
            return ['transition' => $choice, 'data' => []];
        }

        if (is_array($choice) && isset($choice['transition'])) {
            return ['transition' => (string) $choice['transition'], 'data' => (array) ($choice['data'] ?? [])];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<int, array{key: string, label: string|null, available: bool}>
     */
    protected function options(WorkflowDefinition $definition, Step $step, array $scope): array
    {
        return array_map(fn (Transition $transition) => [
            'key' => $transition->getKey(),
            'label' => Workflows::text($transition->getLabel()),
            'to' => $transition->getTo(),
            'available' => $this->conditions->passes($transition->getConditions(), $scope),
        ], $definition->transitionsFrom($step->getKey(), defaults_last: false));
    }

    /**
     * What the actions would do. Actions with an effect on the data (set_data)
     * apply it, so later decisions see it; nothing else runs.
     *
     * @param  array<int, ActionCall>  $calls
     * @return array<int, array{action: string, description: string}>
     */
    protected function describe(array $calls, WorkflowInstance $instance, ?string $step = null, ?string $transition = null): array
    {
        $described = [];

        foreach ($calls as $call) {
            if (! Workflows::hasAction($call->getAction())) {
                $described[] = ['action' => $call->getAction(), 'description' => __('":action" isn\'t an action.', ['action' => $call->getAction()])];

                continue;
            }

            $action = Workflows::action($call->getAction());
            $context = new ActionContext($instance, $call, $step, $transition, simulated: true);

            try {
                $action->simulate($context);
                $description = $action->describe($context);
            } catch (Throwable $exception) {
                $description = $action->name() . ': ' . $exception->getMessage();
            }

            $described[] = ['action' => $action::key(), 'description' => $description];
        }

        return $described;
    }

    /**
     * @return array<string, mixed>
     */
    protected function scope(WorkflowInstance $instance): array
    {
        return ['data' => $instance->data ?? [], 'subject' => $instance->subject, 'starter' => $instance->starter];
    }
}
