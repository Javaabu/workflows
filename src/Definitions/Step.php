<?php

namespace Javaabu\Workflows\Definitions;

/**
 * A step of a workflow:
 *
 * - `start`: where an instance begins. Its form (if any) is filled by whoever starts it.
 *   It moves on by itself, like a decision.
 * - `task`: waits for a person. It has assignees and an optional form; an
 *   assignee takes one of its transitions.
 * - `decision`: moves on by itself, along the first transition whose conditions hold
 *   (the one without conditions when none do).
 * - `action`: runs its actions, then moves on like a decision.
 * - `end`: completes the instance, with an outcome.
 *
 * Every step can run actions when it's entered.
 */
class Step
{
    public const string START = 'start';

    public const string TASK = 'task';

    public const string DECISION = 'decision';

    public const string ACTION = 'action';

    public const string END = 'end';

    public const array TYPES = [self::START, self::TASK, self::DECISION, self::ACTION, self::END];

    /** Types that move on by themselves */
    public const array AUTOMATIC = [self::START, self::DECISION, self::ACTION];

    /** @var string|array<string, string>|null */
    protected string|array|null $name = null;

    /** @var string|array<string, string>|null */
    protected string|array|null $description = null;

    /** @var array<int, Assignee> */
    protected array $assignees = [];

    protected ?string $form = null;

    protected ?int $form_version = null;

    /** @var array<int, ActionCall> */
    protected array $actions = [];

    protected ?string $outcome = null;

    protected ?string $color = null;

    protected ?int $due_in = null;

    /** @var array{x: int, y: int}|null */
    protected ?array $position = null;

    public function __construct(
        protected string $key,
        protected string $type = self::TASK,
    ) {}

    public static function make(string $key, string $type = self::TASK): static
    {
        return new static($key, $type);
    }

    public static function start(string $key = 'start'): static
    {
        return new static($key, self::START);
    }

    public static function task(string $key): static
    {
        return new static($key, self::TASK);
    }

    public static function decision(string $key): static
    {
        return new static($key, self::DECISION);
    }

    public static function action(string $key): static
    {
        return new static($key, self::ACTION);
    }

    public static function end(string $key, ?string $outcome = null): static
    {
        return (new static($key, self::END))->outcome($outcome ?? $key);
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
     * Who acts on the task: Assignee::role('manager'), Assignee::starter(), …
     */
    public function assignTo(Assignee ...$assignees): static
    {
        $this->assignees = [...$this->assignees, ...$assignees];

        return $this;
    }

    /**
     * The javaabu/form-builder form that collects this step's data, by key.
     * Pin a version number, or leave it null to use the form's published version.
     */
    public function form(?string $form, ?int $version = null): static
    {
        $this->form = $form;
        $this->form_version = $version;

        return $this;
    }

    /**
     * Run an action when the step is entered
     *
     * @param  array<string, mixed>  $settings
     */
    public function runs(ActionCall|string $action, array $settings = []): static
    {
        $this->actions[] = $action instanceof ActionCall ? $action : ActionCall::make($action, $settings);

        return $this;
    }

    /**
     * What a completed instance ended with (end steps), e.g. `approved`
     */
    public function outcome(?string $outcome): static
    {
        $this->outcome = $outcome;

        return $this;
    }

    /**
     * A javaabu/forms status colour for the step: success, danger, warning, info, …
     */
    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Tasks of this step are due this many hours after they're created
     */
    public function dueIn(?int $hours): static
    {
        $this->due_in = $hours;

        return $this;
    }

    /**
     * Where the builder draws the step
     */
    public function position(int $x, int $y): static
    {
        $this->position = ['x' => $x, 'y' => $y];

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isAutomatic(): bool
    {
        return in_array($this->type, self::AUTOMATIC, true);
    }

    public function isTask(): bool
    {
        return $this->type === self::TASK;
    }

    public function isEnd(): bool
    {
        return $this->type === self::END;
    }

    public function isStart(): bool
    {
        return $this->type === self::START;
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

    /**
     * @return array<int, Assignee>
     */
    public function getAssignees(): array
    {
        return $this->assignees;
    }

    public function getForm(): ?string
    {
        return $this->form;
    }

    public function getFormVersion(): ?int
    {
        return $this->form_version;
    }

    /**
     * @return array<int, ActionCall>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getDueIn(): ?int
    {
        return $this->due_in;
    }

    /**
     * @return array{x: int, y: int}|null
     */
    public function getPosition(): ?array
    {
        return $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'assignees' => array_map(fn (Assignee $assignee) => $assignee->toArray(), $this->assignees),
            'form' => $this->form,
            'form_version' => $this->form_version,
            'actions' => array_map(fn (ActionCall $action) => $action->toArray(), $this->actions),
            'outcome' => $this->outcome,
            'color' => $this->color,
            'due_in' => $this->due_in,
            'position' => $this->position,
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $array
     */
    public static function fromArray(array $array): static
    {
        $step = new static((string) ($array['key'] ?? ''), (string) ($array['type'] ?? self::TASK));

        $step->name($array['name'] ?? null)
            ->description($array['description'] ?? null)
            ->form(isset($array['form']) && $array['form'] !== '' ? (string) $array['form'] : null, isset($array['form_version']) ? (int) $array['form_version'] : null)
            ->outcome(isset($array['outcome']) && $array['outcome'] !== '' ? (string) $array['outcome'] : null)
            ->color(isset($array['color']) && $array['color'] !== '' ? (string) $array['color'] : null)
            ->dueIn(isset($array['due_in']) && $array['due_in'] !== '' ? (int) $array['due_in'] : null);

        foreach ((array) ($array['assignees'] ?? []) as $assignee) {
            if (is_array($assignee)) {
                $step->assignTo(Assignee::fromArray($assignee));
            }
        }

        foreach ((array) ($array['actions'] ?? []) as $action) {
            if (is_array($action)) {
                $step->runs(ActionCall::fromArray($action));
            }
        }

        if (isset($array['position']['x'], $array['position']['y'])) {
            $step->position((int) round((float) $array['position']['x']), (int) round((float) $array['position']['y']));
        }

        return $step;
    }
}
