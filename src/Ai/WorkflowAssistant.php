<?php

namespace Javaabu\Workflows\Ai;

use ArrayAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Builder\WorkflowForms;
use Javaabu\Workflows\Definitions\DefinitionValidator;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Support\Translations;
use Javaabu\Workflows\WorkflowsManager;
use Laravel\Ai\Contracts\Agent;
use RuntimeException;

/**
 * Builds a workflow, or changes one, from a description, with laravel/ai. The
 * answer is checked like the builder's (with a retry that shows the agent its
 * errors) and saved as a draft for someone to review and publish.
 */
class WorkflowAssistant
{
    public function __construct(
        protected WorkflowsManager $workflows,
        protected DefinitionValidator $validator,
        protected BuilderData $data,
    ) {}

    /**
     * Whether laravel/ai is installed and the assistant is on
     */
    public static function available(): bool
    {
        return interface_exists(Agent::class) && (bool) config('workflows.assistant.enabled', true);
    }

    /**
     * Build a new workflow from a description, or change $workflow's draft as it describes
     *
     * @return array{workflow: Workflow, summary: string}
     */
    public function build(string $description, ?Workflow $workflow = null, ?Model $author = null): array
    {
        if (! static::available()) {
            throw new RuntimeException('Install laravel/ai to use the workflow assistant.');
        }

        $current = $workflow ? (string) json_encode($workflow->definition(draft: true)?->toArray(), JSON_UNESCAPED_UNICODE) : null;
        $agent = new WorkflowAssistantAgent($this->instructions());

        $prompt = $this->prompt($description, $current);
        $attempts = max(1, (int) config('workflows.assistant.attempts', 2));
        $errors = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $response = $agent->prompt(
                $prompt,
                provider: config('workflows.assistant.provider'),
                model: config('workflows.assistant.model'),
                timeout: config('workflows.assistant.timeout'),
            );

            $answer = $response instanceof ArrayAccess ? $response : [];
            $json = (string) ($answer['workflow'] ?? '');

            $errors = $this->errors($json, $workflow);

            if ($errors !== []) {
                $prompt = $this->retryPrompt($description, $current, $json, $errors);

                continue;
            }

            return [
                'workflow' => $this->save(json_decode($json, true), $workflow, $author),
                'summary' => trim((string) ($answer['summary'] ?? '')) ?: __('The assistant changed the draft.'),
            ];
        }

        throw ValidationException::withMessages([
            'description' => [__('The assistant couldn\'t make a valid workflow. Try describing it differently.'), ...array_slice($errors, 0, 5)],
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function errors(string $json, ?Workflow $workflow): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! is_array($decoded['steps'] ?? null)) {
            return ['The answer isn\'t a workflow in the JSON format.'];
        }

        $errors = [];

        if (! $workflow) {
            $key = (string) ($decoded['key'] ?? '');

            if (! preg_match(WorkflowForms::KEY_PATTERN, $key)) {
                $errors[] = 'The workflow needs a key: lowercase letters, numbers, dashes and underscores.';
            } elseif ($this->workflows->model('workflow')::query()->withTrashed()->where('key', $key)->exists()) {
                $errors[] = "Another workflow already has the key {$key}: choose another.";
            }
        }

        foreach ($this->validator->errors(WorkflowDefinition::fromArray($decoded, $workflow?->key ?? 'workflow')) as $key => $messages) {
            foreach ($messages as $message) {
                $errors[] = "{$key}: {$message}";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    protected function save(array $decoded, ?Workflow $workflow, ?Model $author): Workflow
    {
        return DB::transaction(function () use ($decoded, $workflow, $author) {
            $definition = WorkflowDefinition::fromArray($decoded, $workflow?->key);

            if (! $workflow) {
                /** @var Workflow $workflow */
                $workflow = $this->workflows->model('workflow')::make();
                $workflow->key = $definition->getKey();
                $workflow->name = (string) ($this->plain($definition->getName()) ?? $definition->getKey());
                $workflow->description = $this->plain($definition->getDescription());
                $workflow->subject_type = $definition->getSubject();
                $workflow->save();

                Translations::saveModelText($workflow, ['name' => $definition->getName(), 'description' => $definition->getDescription()]);
            }

            $workflow->draft($author)->replaceStructure($this->layout($definition), validate: false);

            return $workflow->refresh();
        });
    }

    /**
     * Steps without positions are laid out left to right, by their distance from the start
     */
    protected function layout(WorkflowDefinition $definition): WorkflowDefinition
    {
        $start = $definition->getStartStep();
        $depth = $start ? [$start->getKey() => 0] : [];
        $queue = $start ? [$start->getKey()] : [];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($definition->transitionsFrom($current) as $transition) {
                $to = (string) $transition->getTo();

                if (! isset($depth[$to])) {
                    $depth[$to] = $depth[$current] + 1;
                    $queue[] = $to;
                }
            }
        }

        $rows = [];

        foreach ($definition->getSteps() as $step) {
            if ($step->getPosition() !== null) {
                continue;
            }

            $column = $depth[$step->getKey()] ?? (max($depth ?: [0]) + 1);
            $row = $rows[$column] = ($rows[$column] ?? -1) + 1;

            $step->position(40 + $column * 240, 60 + $row * 140);
        }

        return $definition;
    }

    /**
     * @param  string|array<string, string>|null  $text
     */
    protected function plain(string|array|null $text): ?string
    {
        if (! is_array($text)) {
            return $text;
        }

        return $text[Translations::defaultLocale()] ?? (reset($text) ?: null);
    }

    protected function prompt(string $description, ?string $current): string
    {
        if ($current === null) {
            return "Design this workflow:\n\n{$description}";
        }

        return "Change this workflow as described. Keep everything that isn't mentioned, including step positions.\n\nThe workflow now:\n{$current}\n\nThe changes:\n{$description}";
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function retryPrompt(string $description, ?string $current, string $answer, array $errors): string
    {
        return $this->prompt($description, $current)
            . "\n\nYour last answer had these errors. Fix them and answer with the whole workflow again:\n- "
            . implode("\n- ", $errors)
            . "\n\nYour last answer:\n{$answer}";
    }

    /**
     * What the agent knows: the format, the step types, assignees, actions, forms and rules
     */
    public function instructions(): string
    {
        $catalog = $this->data->catalog();

        $actions = implode("\n", array_map(
            fn (array $action) => "  - {$action['key']} ({$action['name']}): settings " . (implode(', ', array_keys($action['fields'])) ?: 'none'),
            $catalog['actions'],
        ));

        $forms = $catalog['forms'] === []
            ? '  (none: leave "form" out)'
            : implode("\n", array_map(fn (array $form) => "  - {$form['key']} ({$form['name']})", $catalog['forms']));

        $roles = $catalog['roles'] === [] ? '(none yet)' : implode(', ', $catalog['roles']);

        $custom = array_keys($this->workflows->customAssignees());
        $assignees = 'starter, role (value: a role name), permission (value: a permission name), subject (value: a relation of the subject, like manager), step (value: an earlier step\'s key: whoever did it), user (value: type:key)'
            . ($custom ? ', ' . implode(', ', $custom) : '');

        $locales = Translations::locales();
        $text = count($locales) > 1
            ? 'Write every name, description and label as an object of language code => text, in each of these languages: ' . implode(', ', $locales) . '. For Dhivehi (dv) write Thaana.'
            : 'Write names, descriptions and labels as plain strings in the language of the request.';

        return <<<TEXT
        You design workflows for javaabu/workflows, a Laravel workflow engine. You answer with the whole workflow as JSON.

        The format:
        {"workflows": 1, "key": "leave-request", "name": "Leave request", "description": "…", "steps": [
          {"key": "start", "type": "start", "name": "Submitted", "form": "leave-request"},
          {"key": "review", "type": "task", "name": "Manager review", "assignees": [{"type": "subject", "value": "manager"}], "due_in": 48},
          {"key": "long_leave", "type": "decision", "name": "Long leave?"},
          {"key": "notify_hr", "type": "action", "name": "Tell HR", "actions": [{"action": "notify", "settings": {"to": [{"type": "role", "value": "hr"}], "title": "Long leave approved"}}]},
          {"key": "approved", "type": "end", "name": "Approved", "outcome": "approved", "color": "success"},
          {"key": "rejected", "type": "end", "name": "Rejected", "outcome": "rejected", "color": "danger"}
        ], "transitions": [
          {"key": "submit", "from": "start", "to": "review"},
          {"key": "approve", "from": "review", "to": "long_leave", "label": "Approve", "color": "success"},
          {"key": "reject", "from": "review", "to": "rejected", "label": "Reject", "color": "danger", "comment": "required", "skip_form": true},
          {"key": "long", "from": "long_leave", "to": "notify_hr", "conditions": {"match": "all", "rules": [{"field": "days", "operator": ">", "value": 5}]}},
          {"key": "short", "from": "long_leave", "to": "approved"},
          {"key": "after_hr", "from": "notify_hr", "to": "approved"}
        ]}

        Rules:
        - Keys are lowercase snake_case, start with a letter, and are unique among steps and among transitions. The workflow key may also have dashes.
        - Step types: start (exactly one; where it begins, moves on by itself), task (waits for a person, who picks one of its transitions), decision (moves on by itself along the first transition whose conditions hold, or the one without conditions), action (runs its actions, then moves on like a decision), end (at least one; finishes with an outcome).
        - Every step but an end needs a transition out, every step must be able to reach an end, nothing leads to the start, and nothing leaves an end. An automatic step (start, decision, action) has at most one transition without conditions.
        - Tasks have assignees: {$assignees}. Known roles: {$roles}.
        - Transitions from tasks are buttons: give them a label and a colour (primary, secondary, success, info, warning, danger). "comment" is none, optional or required. "skip_form": true takes it without filling the step's form (e.g. Reject).
        - Conditions: {"match": "all" | "any", "rules": [{"field": "days", "operator": ">", "value": 5}]}. Fields are the workflow's data (what forms collected), subject.<attribute> or starter.<attribute>. Operators: =, !=, >, >=, <, <=, in, not_in (value: a list), contains, filled, blank (no value).
        - Steps and transitions can run actions: {"action": "<key>", "settings": {...}}. Text settings can use {{ days }} or {{ subject.title }}. Actions:
        {$actions}
        - The start step and tasks can collect data with a form (by key). Forms:
        {$forms}
        - {$text}
        - Keep it as simple as the description allows. Name every step.
        TEXT;
    }
}
