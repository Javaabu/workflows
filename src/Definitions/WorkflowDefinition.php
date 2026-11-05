<?php

namespace Javaabu\Workflows\Definitions;

use Illuminate\Database\Eloquent\Relations\Relation;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Workflow;

/**
 * A workflow in code, for seeders and the JSON format:
 *
 * ```php
 * Workflows::define('leave-request')
 *     ->name(['en' => 'Leave request', 'dv' => 'ޗުއްޓީގެ އެދުން'])
 *     ->for(LeaveRequest::class)
 *     ->steps([
 *         Step::start()->form('leave-request'),
 *         Step::task('review')->name('Manager review')->assignTo(Assignee::role('manager')),
 *         Step::end('approved'),
 *         Step::end('rejected'),
 *     ])
 *     ->transitions([
 *         Transition::make('submit')->from('start')->to('review'),
 *         Transition::make('approve')->from('review')->to('approved')->label('Approve')->color('success'),
 *         Transition::make('reject')->from('review')->to('rejected')->label('Reject')->color('danger')->requireComment()->skipForm(),
 *     ])
 *     ->sync();
 * ```
 */
class WorkflowDefinition
{
    /** The version of the JSON format */
    public const int FORMAT = 1;

    /** @var string|array<string, string>|null */
    protected string|array|null $name = null;

    /** @var string|array<string, string>|null */
    protected string|array|null $description = null;

    protected ?string $subject = null;

    protected bool $locked = false;

    /** @var array<int, Step> */
    protected array $steps = [];

    /** @var array<int, Transition> */
    protected array $transitions = [];

    public function __construct(
        protected string $key,
    ) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    /**
     * @param  string|array<string, string>|null  $name  text, or a map of locale => text
     */
    public function name(string|array|null $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param  string|array<string, string>|null  $description
     */
    public function description(string|array|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * The model instances of this workflow are about: a class or morph alias
     */
    public function for(?string $model): static
    {
        $this->subject = $model && class_exists($model) ? Relation::getMorphAlias($model) : $model;

        return $this;
    }

    /**
     * Only change it in code: the builder shows it read-only
     */
    public function locked(bool $locked = true): static
    {
        $this->locked = $locked;

        return $this;
    }

    /**
     * @param  array<int, Step>  $steps
     */
    public function steps(array $steps): static
    {
        $this->steps = array_values($steps);

        return $this;
    }

    public function step(Step $step): static
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * @param  array<int, Transition>  $transitions
     */
    public function transitions(array $transitions): static
    {
        $this->transitions = array_values($transitions);

        return $this;
    }

    public function transition(Transition $transition): static
    {
        $this->transitions[] = $transition;

        return $this;
    }

    /**
     * Save it: create the workflow, or give it a new version when the steps or
     * transitions changed. See WorkflowsManager::sync().
     */
    public function sync(?bool $publish = null): Workflow
    {
        return Workflows::sync($this, $publish);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string|array<string, string>|null
     */
    public function getName(): string|array|null
    {
        return $this->name;
    }

    /**
     * @return string|array<string, string>|null
     */
    public function getDescription(): string|array|null
    {
        return $this->description;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getStep(string $key): ?Step
    {
        foreach ($this->steps as $step) {
            if ($step->getKey() === $key) {
                return $step;
            }
        }

        return null;
    }

    public function getStartStep(): ?Step
    {
        foreach ($this->steps as $step) {
            if ($step->isStart()) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<int, Transition>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getTransition(string $key): ?Transition
    {
        foreach ($this->transitions as $transition) {
            if ($transition->getKey() === $key) {
                return $transition;
            }
        }

        return null;
    }

    /**
     * The transitions out of a step: those with conditions first, in order,
     * then those without, so a transition without conditions is the default
     *
     * Pass $defaults_last false for the order they were defined in (the buttons of a task).
     *
     * @return array<int, Transition>
     */
    public function transitionsFrom(string $step, bool $defaults_last = true): array
    {
        $from = array_values(array_filter($this->transitions, fn (Transition $transition) => $transition->getFrom() === $step));

        if (! $defaults_last) {
            return $from;
        }

        usort($from, fn (Transition $a, Transition $b) => (int) $b->hasConditions() <=> (int) $a->hasConditions());

        return $from;
    }

    /**
     * @return array<int, Transition>
     */
    public function transitionsTo(string $step): array
    {
        return array_values(array_filter($this->transitions, fn (Transition $transition) => $transition->getTo() === $step));
    }

    /**
     * The steps and transitions: what a version stores
     *
     * @return array{workflows: int, steps: array<int, array<string, mixed>>, transitions: array<int, array<string, mixed>>}
     */
    public function structure(): array
    {
        return [
            'workflows' => self::FORMAT,
            'steps' => array_map(fn (Step $step) => $step->toArray(), $this->steps),
            'transitions' => array_map(fn (Transition $transition) => $transition->toArray(), $this->transitions),
        ];
    }

    /**
     * Everything: the export format
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'workflows' => self::FORMAT,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'subject' => $this->subject,
            'locked' => $this->locked,
            'steps' => $this->structure()['steps'],
            'transitions' => $this->structure()['transitions'],
        ];
    }

    /**
     * @param  array<string, mixed>  $array  the export format, or a version's structure (with $key)
     */
    public static function fromArray(array $array, ?string $key = null): static
    {
        $definition = new static($key ?? (string) ($array['key'] ?? ''));

        $definition->name($array['name'] ?? null)
            ->description($array['description'] ?? null)
            ->locked((bool) ($array['locked'] ?? false));

        $definition->subject = isset($array['subject']) && $array['subject'] !== '' ? (string) $array['subject'] : null;

        foreach ((array) ($array['steps'] ?? []) as $step) {
            if (is_array($step)) {
                $definition->step(Step::fromArray($step));
            }
        }

        foreach ((array) ($array['transitions'] ?? []) as $transition) {
            if (is_array($transition)) {
                $definition->transition(Transition::fromArray($transition));
            }
        }

        return $definition;
    }
}
