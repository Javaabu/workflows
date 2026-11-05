<?php

namespace Javaabu\Workflows\Definitions;

use Illuminate\Validation\ValidationException;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\Workflows\Support\Translations;
use Javaabu\Workflows\WorkflowsManager;

/**
 * Checks a workflow's steps and transitions before they're saved, whether they
 * come from code, the builder, an import or the assistant. Errors are keyed by
 * what they're about (`steps.review`, `transitions.approve`, `definition`), so
 * the builder can point at the step or transition.
 */
class DefinitionValidator
{
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public const string FIELD_PATTERN = '/^[a-z0-9_]+(\.[a-z0-9_*]+)*$/';

    /** @var array<string, array<int, string>> */
    protected array $errors = [];

    public function __construct(
        protected WorkflowsManager $workflows,
    ) {}

    /**
     * @return array<string, array<int, string>> errors, keyed by what they're about
     */
    public function errors(WorkflowDefinition|array $definition): array
    {
        $this->errors = [];

        $definition = $definition instanceof WorkflowDefinition ? $definition : WorkflowDefinition::fromArray($definition, 'workflow');

        $this->checkSteps($definition);
        $this->checkTransitions($definition);

        if ($this->errors === []) {
            $this->checkPaths($definition);
        }

        return $this->errors;
    }

    /**
     * @throws ValidationException
     */
    public function validate(WorkflowDefinition|array $definition): void
    {
        $errors = $this->errors($definition);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Steps no path from the start reaches. They're allowed (a draft can be
     * half built) but the builder warns about them.
     *
     * @return array<int, string>
     */
    public function unreachable(WorkflowDefinition $definition): array
    {
        $start = $definition->getStartStep();

        if (! $start) {
            return [];
        }

        $reached = $this->reachableFrom($definition, $start->getKey());

        return array_values(array_diff(array_map(fn (Step $step) => $step->getKey(), $definition->getSteps()), $reached));
    }

    protected function checkSteps(WorkflowDefinition $definition): void
    {
        $keys = [];
        $starts = 0;
        $ends = 0;

        if ($definition->getSteps() === []) {
            $this->add('definition', __('A workflow needs a start step and an end step.'));

            return;
        }

        foreach ($definition->getSteps() as $index => $step) {
            $key = $step->getKey();
            $path = 'steps.' . ($key !== '' ? $key : $index);

            if (! preg_match(self::KEY_PATTERN, $key)) {
                $this->add($path, __('A step\'s key is lowercase letters, numbers and underscores, starting with a letter.'));
            }

            if (in_array($key, $keys, true)) {
                $this->add($path, __('Two steps have the key :key.', ['key' => $key]));
            }

            $keys[] = $key;

            if (! in_array($step->getType(), Step::TYPES, true)) {
                $this->add($path, __('":type" isn\'t a step type.', ['type' => $step->getType()]));

                continue;
            }

            $starts += (int) $step->isStart();
            $ends += (int) $step->isEnd();

            $this->checkText($path, $step->getName());
            $this->checkText($path, $step->getDescription());
            $this->checkAssignees($definition, $step, $path);
            $this->checkForm($step, $path);
            $this->checkActions($step->getActions(), $path);

            if ($step->getDueIn() !== null && $step->getDueIn() < 1) {
                $this->add($path, __('A due time is a number of hours.'));
            }
        }

        if ($starts !== 1) {
            $this->add('definition', __('A workflow has exactly one start step.'));
        }

        if ($ends < 1) {
            $this->add('definition', __('A workflow needs at least one end step.'));
        }
    }

    protected function checkAssignees(WorkflowDefinition $definition, Step $step, string $path): void
    {
        if ($step->getAssignees() !== [] && ! $step->isTask()) {
            $this->add($path, __('Only tasks have assignees.'));

            return;
        }

        foreach ($step->getAssignees() as $assignee) {
            $type = $assignee->getType();

            if (! in_array($type, Assignee::BUILT_IN, true) && ! $this->workflows->hasAssigneeResolver($type)) {
                $this->add($path, __('":type" isn\'t an assignee type.', ['type' => $type]));

                continue;
            }

            $needs_value = in_array($type, [Assignee::USER, Assignee::ROLE, Assignee::PERMISSION, Assignee::SUBJECT, Assignee::STEP], true);

            if ($needs_value && ($assignee->getValue() === null || $assignee->getValue() === '')) {
                $this->add($path, __('Choose who the task is assigned to.'));

                continue;
            }

            if ($type === Assignee::USER && ! str_contains((string) $assignee->getValue(), ':')) {
                $this->add($path, __('A user assignee is the user\'s type and key, like admin:4.'));
            }

            if ($type === Assignee::STEP && ! $definition->getStep((string) $assignee->getValue())) {
                $this->add($path, __('The task is assigned to whoever did :step, which isn\'t a step.', ['step' => $assignee->getValue()]));
            }
        }
    }

    protected function checkForm(Step $step, string $path): void
    {
        $form = $step->getForm();

        if ($form === null) {
            return;
        }

        if (! $step->isStart() && ! $step->isTask()) {
            $this->add($path, __('Only the start step and tasks collect data with a form.'));

            return;
        }

        $model = FormBuilder::model('form')::query()->where('key', $form)->first();

        if (! $model) {
            $this->add($path, __('There\'s no form with the key :form.', ['form' => $form]));

            return;
        }

        if ($step->getFormVersion() !== null) {
            $published = $model->versions()->where('number', $step->getFormVersion())->whereNotNull('published_at')->exists();

            if (! $published) {
                $this->add($path, __('Version :version of :form isn\'t published.', ['version' => $step->getFormVersion(), 'form' => $form]));
            }
        }
    }

    /**
     * @param  array<int, ActionCall>  $actions
     */
    protected function checkActions(array $actions, string $path): void
    {
        foreach ($actions as $call) {
            if (! $this->workflows->hasAction($call->getAction())) {
                $this->add($path, __('":action" isn\'t an action.', ['action' => $call->getAction()]));

                continue;
            }

            foreach ($this->workflows->action($call->getAction())->validateSettings($call->getSettings()) as $message) {
                $this->add($path, $message);
            }
        }
    }

    protected function checkTransitions(WorkflowDefinition $definition): void
    {
        $keys = [];

        foreach ($definition->getTransitions() as $index => $transition) {
            $key = $transition->getKey();
            $path = 'transitions.' . ($key !== '' ? $key : $index);

            if (! preg_match(self::KEY_PATTERN, $key)) {
                $this->add($path, __('A transition\'s key is lowercase letters, numbers and underscores, starting with a letter.'));
            }

            if (in_array($key, $keys, true)) {
                $this->add($path, __('Two transitions have the key :key.', ['key' => $key]));
            }

            $keys[] = $key;

            $from = $transition->getFrom() ? $definition->getStep($transition->getFrom()) : null;
            $to = $transition->getTo() ? $definition->getStep($transition->getTo()) : null;

            if (! $from) {
                $this->add($path, __('The transition :key doesn\'t start at a step.', ['key' => $key]));
            }

            if (! $to) {
                $this->add($path, __('The transition :key doesn\'t lead to a step.', ['key' => $key]));
            }

            if ($from?->isEnd()) {
                $this->add($path, __('Nothing leaves an end step.'));
            }

            if ($to?->isStart()) {
                $this->add($path, __('Nothing leads back to the start step.'));
            }

            if ($from && $to && $from->getKey() === $to->getKey() && $from->isAutomatic()) {
                $this->add($path, __('An automatic step can\'t lead to itself.'));
            }

            $this->checkText($path, $transition->getLabel());
            $this->checkConditions($transition->getConditions(), $path);
            $this->checkActions($transition->getActions(), $path);
        }

        foreach ($definition->getSteps() as $step) {
            if ($step->isEnd() || ! in_array($step->getType(), Step::TYPES, true)) {
                continue;
            }

            $out = $definition->transitionsFrom($step->getKey());

            if ($out === []) {
                $this->add("steps.{$step->getKey()}", __('Nothing leaves :step: add a transition from it.', ['step' => $step->getKey()]));

                continue;
            }

            $defaults = array_filter($out, fn (Transition $transition) => ! $transition->hasConditions());

            if ($step->isAutomatic() && count($defaults) > 1) {
                $this->add("steps.{$step->getKey()}", __(':step moves on by itself, so only one of its transitions can be without conditions.', ['step' => $step->getKey()]));
            }
        }
    }

    protected function checkConditions(Conditions $conditions, string $path): void
    {
        foreach ($conditions->getRules() as $rule) {
            if (! preg_match(self::FIELD_PATTERN, $rule->getField())) {
                $this->add($path, __('":field" isn\'t a field a condition can check.', ['field' => $rule->getField()]));
            }

            $value = $rule->getValue();

            if (in_array($rule->getOperator(), ['in', 'not_in'], true) && ! is_array($value)) {
                $this->add($path, __('":operator" needs a list of values.', ['operator' => $rule->getOperator()]));
            }

            if (in_array($rule->getOperator(), ['>', '>=', '<', '<='], true) && ! is_numeric($value) && ! is_string($value)) {
                $this->add($path, __('":operator" compares with a number or a date.', ['operator' => $rule->getOperator()]));
            }
        }
    }

    /**
     * Every step on a path from the start must be able to reach an end
     */
    protected function checkPaths(WorkflowDefinition $definition): void
    {
        $ends = array_map(fn (Step $step) => $step->getKey(), array_filter($definition->getSteps(), fn (Step $step) => $step->isEnd()));

        foreach ($definition->getSteps() as $step) {
            if ($step->isEnd()) {
                continue;
            }

            if (array_intersect($this->reachableFrom($definition, $step->getKey()), $ends) === []) {
                $this->add("steps.{$step->getKey()}", __('No path from :step reaches an end step.', ['step' => $step->getKey()]));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function reachableFrom(WorkflowDefinition $definition, string $key): array
    {
        $seen = [$key];
        $queue = [$key];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($definition->transitionsFrom($current) as $transition) {
                $to = (string) $transition->getTo();

                if (! in_array($to, $seen, true)) {
                    $seen[] = $to;
                    $queue[] = $to;
                }
            }
        }

        return $seen;
    }

    protected function checkText(string $path, mixed $text): void
    {
        if ($text === null || is_string($text)) {
            return;
        }

        if (! is_array($text) || ! Translations::isLocaleMap($text)) {
            $this->add($path, __('Text is a string, or a map of language codes to strings.'));
        }
    }

    protected function add(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }
}
