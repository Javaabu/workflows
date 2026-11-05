<?php

namespace Javaabu\Workflows\Definitions;

/**
 * A way from one step to another. From a task, it's a button the assignee
 * presses (`Approve`, `Reject`); from an automatic step, it's taken by itself
 * when its conditions hold. It can ask for a comment, skip the step's form and
 * run actions when it's taken.
 */
class Transition
{
    public const string COMMENT_NONE = 'none';

    public const string COMMENT_OPTIONAL = 'optional';

    public const string COMMENT_REQUIRED = 'required';

    public const array COMMENTS = [self::COMMENT_NONE, self::COMMENT_OPTIONAL, self::COMMENT_REQUIRED];

    protected ?string $from = null;

    protected ?string $to = null;

    /** @var string|array<string, string>|null */
    protected string|array|null $label = null;

    protected ?string $color = null;

    protected Conditions $conditions;

    protected string $comment = self::COMMENT_OPTIONAL;

    protected bool $skips_form = false;

    /** @var array<int, ActionCall> */
    protected array $actions = [];

    public function __construct(
        protected string $key,
    ) {
        $this->conditions = new Conditions;
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function from(string $step): static
    {
        $this->from = $step;

        return $this;
    }

    public function to(string $step): static
    {
        $this->to = $step;

        return $this;
    }

    /**
     * @param  string|array<string, string>|null  $label  text, or a map of locale => text
     */
    public function label(string|array|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A javaabu/forms button colour: primary, success, danger, …
     */
    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Only when a rule holds: `when('days', '>', 5)`, `when('type', ['annual', 'sick'])`,
     * `when('subject.amount', '>=', 1000)`, `when('reason')` (filled)
     */
    public function when(string $field, mixed $operator = null, mixed $value = null): static
    {
        $this->conditions->add(Condition::parse($field, $operator, $value, func_num_args()));

        return $this;
    }

    /**
     * Holds when any of its rules hold, instead of all of them
     */
    public function whenAny(): static
    {
        $this->conditions->match(Conditions::ANY);

        return $this;
    }

    public function conditions(Conditions $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * Whether people taking it write a comment: none, optional (the default) or required
     */
    public function comment(string $comment): static
    {
        $this->comment = in_array($comment, self::COMMENTS, true) ? $comment : self::COMMENT_OPTIONAL;

        return $this;
    }

    public function requireComment(): static
    {
        return $this->comment(self::COMMENT_REQUIRED);
    }

    /**
     * Take it without filling the step's form, e.g. Reject
     */
    public function skipForm(bool $skip = true): static
    {
        $this->skips_form = $skip;

        return $this;
    }

    /**
     * Run an action when it's taken
     *
     * @param  array<string, mixed>  $settings
     */
    public function runs(ActionCall|string $action, array $settings = []): static
    {
        $this->actions[] = $action instanceof ActionCall ? $action : ActionCall::make($action, $settings);

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    /**
     * @return string|array<string, string>|null
     */
    public function getLabel(): string|array|null
    {
        return $this->label;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function getConditions(): Conditions
    {
        return $this->conditions;
    }

    public function hasConditions(): bool
    {
        return ! $this->conditions->isEmpty();
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function skipsForm(): bool
    {
        return $this->skips_form;
    }

    /**
     * @return array<int, ActionCall>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'from' => $this->from,
            'to' => $this->to,
            'label' => $this->label,
            'color' => $this->color,
            'conditions' => $this->conditions->isEmpty() ? null : $this->conditions->toArray(),
            'comment' => $this->comment === self::COMMENT_OPTIONAL ? null : $this->comment,
            'skip_form' => $this->skips_form ?: null,
            'actions' => array_map(fn (ActionCall $action) => $action->toArray(), $this->actions),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $array
     */
    public static function fromArray(array $array): static
    {
        $transition = new static((string) ($array['key'] ?? ''));

        $transition->label($array['label'] ?? null)
            ->color(isset($array['color']) && $array['color'] !== '' ? (string) $array['color'] : null)
            ->conditions(Conditions::fromArray(is_array($array['conditions'] ?? null) ? $array['conditions'] : null))
            ->comment((string) ($array['comment'] ?? self::COMMENT_OPTIONAL))
            ->skipForm((bool) ($array['skip_form'] ?? false));

        if (isset($array['from'])) {
            $transition->from((string) $array['from']);
        }

        if (isset($array['to'])) {
            $transition->to((string) $array['to']);
        }

        foreach ((array) ($array['actions'] ?? []) as $action) {
            if (is_array($action)) {
                $transition->runs(ActionCall::fromArray($action));
            }
        }

        return $transition;
    }
}
