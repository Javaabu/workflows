<?php

namespace Javaabu\Workflows\Builder;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\BuiltIn\NotifyAction;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Condition;
use Javaabu\Workflows\Definitions\DefinitionValidator;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Models\WorkflowVersion;
use Javaabu\Workflows\Routing\WorkflowRoutes;
use Javaabu\Workflows\Support\Strings;
use Javaabu\Workflows\Support\Translations;
use Javaabu\Workflows\WorkflowsManager;

/**
 * The data the pages show, the same for Blade and React: workflows, versions,
 * the builder's catalog, instances, tasks and history
 */
class BuilderData
{
    public const array COLORS = ['primary', 'secondary', 'success', 'info', 'warning', 'danger'];

    public function __construct(
        protected WorkflowsManager $workflows,
        protected DefinitionValidator $validator,
    ) {}

    /**
     * A workflow as the lists show it
     *
     * @return array<string, mixed>
     */
    public function workflow(Request $request, Workflow $workflow): array
    {
        $prefix = WorkflowRoutes::namePrefix($request);
        $route = fn (string $action, mixed $parameters = []) => app('router')->has("{$prefix}.{$action}") ? route("{$prefix}.{$action}", $parameters) : null;
        $status = $this->status($workflow);

        return [
            'id' => $workflow->getKey(),
            'key' => $workflow->key,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'subject_type' => $workflow->subject_type,
            'subject_label' => $this->subjectLabel($workflow->subject_type),
            'status' => $status,
            'status_label' => Strings::workflowStatus($status),
            'is_locked' => $workflow->is_locked,
            'is_active' => $workflow->is_active,
            'published_version' => $workflow->publishedVersion?->number,
            'draft_version' => $workflow->draftVersion?->number,
            'has_draft' => $workflow->draftVersion !== null,
            'instances_count' => (int) ($workflow->instances_count ?? $workflow->instances()->count()),
            'active_count' => (int) ($workflow->active_count ?? $workflow->instances()->active()->count()),
            'updated_at' => $workflow->updated_at?->toIso8601String(),
            'urls' => array_filter([
                'show' => $route('show', $workflow->key),
                'edit' => $route('edit', $workflow->key),
                'test' => $route('test', $workflow->key),
                'instances' => $route('instances.index', $workflow->key),
                'versions' => $route('versions', $workflow->key),
                'export' => $route('export', $workflow->key),
                'destroy' => $route('destroy', $workflow->key),
            ]),
        ];
    }

    public function status(Workflow $workflow): string
    {
        if (! $workflow->isPublished()) {
            return 'draft';
        }

        return $workflow->draftVersion ? 'changed' : 'published';
    }

    /**
     * @return array<string, mixed>
     */
    public function version(WorkflowVersion $version, ?Workflow $workflow = null): array
    {
        $author = $version->author;

        return [
            'id' => $version->getKey(),
            'number' => $version->number,
            'is_draft' => $version->isDraft(),
            'is_current' => $workflow?->published_version_id === $version->getKey(),
            'published_at' => $version->published_at?->toIso8601String(),
            'published_at_label' => $version->published_at?->locale(app()->getLocale())->translatedFormat((string) config('workflows.datetime_format', 'j F Y, H:i')),
            'author' => $author instanceof Model ? $this->name($author) : null,
            'instances_count' => $version->instances()->count(),
            'steps_count' => count($version->definition['steps'] ?? []),
        ];
    }

    /**
     * The builder: the definition to edit, what it can be built from, and its problems
     *
     * @return array<string, mixed>
     */
    public function builder(Workflow $workflow, bool $editable = true): array
    {
        $definition = $workflow->definition(draft: true) ?? WorkflowDefinition::make($workflow->key);

        return [
            'definition' => $definition->structure(),
            ...$this->problems($definition),
            'catalog' => $this->catalog(),
            'editable' => $editable,
        ];
    }

    /**
     * @return array{errors: array<string, array<int, string>>, unreachable: array<int, string>}
     */
    public function problems(WorkflowDefinition $definition): array
    {
        return [
            'errors' => $this->validator->errors($definition),
            'unreachable' => $this->validator->unreachable($definition),
        ];
    }

    /**
     * What the builder offers: step types, actions and their settings, assignee
     * types, roles, permissions, forms, condition operators, colours and locales
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return [
            'step_types' => array_map(fn (string $type) => [
                'type' => $type,
                'label' => Strings::stepType($type),
                'description' => match ($type) {
                    Step::START => __('Where it begins. Its form is filled by whoever starts it.'),
                    Step::TASK => __('Waits for someone to choose what happens next.'),
                    Step::DECISION => __('Goes one way or another by itself, by the conditions.'),
                    Step::ACTION => __('Does something by itself, then moves on.'),
                    Step::END => __('Where it finishes, with an outcome.'),
                    default => null,
                },
                'automatic' => in_array($type, Step::AUTOMATIC, true),
            ], Step::TYPES),
            'actions' => array_values(array_map(fn (Action $action) => [
                'key' => $action::key(),
                'name' => $action->name(),
                'description' => $action->description(),
                'icon' => $action->icon(),
                'fields' => $action->settingsFields(),
            ], $this->workflows->actions())),
            'assignee_types' => $this->assigneeTypes(),
            'roles' => $this->names(config('permission.models.role')),
            'permissions' => $this->names(config('permission.models.permission')),
            'forms' => $this->forms(),
            'operators' => array_map(fn (string $operator, string $label) => [
                'value' => $operator,
                'label' => __($label),
                'unary' => in_array($operator, Condition::UNARY, true),
                'list' => in_array($operator, ['in', 'not_in'], true),
            ], array_keys(Condition::OPERATORS), Condition::OPERATORS),
            'colors' => array_map(fn (string $color) => ['value' => $color, 'label' => $this->colorLabel($color)], self::COLORS),
            'comments' => [
                ['value' => Transition::COMMENT_NONE, 'label' => __('No comment')],
                ['value' => Transition::COMMENT_OPTIONAL, 'label' => __('Optional comment')],
                ['value' => Transition::COMMENT_REQUIRED, 'label' => __('Comment required')],
            ],
            'locales' => array_map(fn (string $code) => [
                'code' => $code,
                'name' => Translations::localeName($code),
                'rtl' => Translations::isRtl($code),
            ], Translations::locales()),
        ];
    }

    /**
     * @return array<int, array{type: string, label: string, needs_value: bool, help: string|null}>
     */
    public function assigneeTypes(): array
    {
        $types = [
            ['type' => Assignee::STARTER, 'label' => __('Whoever started it'), 'needs_value' => false, 'help' => null],
            ['type' => Assignee::ROLE, 'label' => __('Everyone with a role'), 'needs_value' => true, 'help' => null],
            ['type' => Assignee::PERMISSION, 'label' => __('Everyone with a permission'), 'needs_value' => true, 'help' => null],
            ['type' => Assignee::SUBJECT, 'label' => __('Someone related to the subject'), 'needs_value' => true, 'help' => __('A relation or attribute of the subject, like manager.')],
            ['type' => Assignee::STEP, 'label' => __('Whoever did an earlier step'), 'needs_value' => true, 'help' => null],
            ['type' => Assignee::USER, 'label' => __('A user'), 'needs_value' => true, 'help' => __('Their type and key, like admin:4.')],
        ];

        foreach ($this->workflows->customAssignees() as $type => $label) {
            $types[] = ['type' => $type, 'label' => $label ?: str($type)->headline()->toString(), 'needs_value' => false, 'help' => null];
        }

        $types[] = ['type' => NotifyAction::ASSIGNEES, 'label' => __('The task\'s assignees'), 'needs_value' => false, 'help' => null, 'notify_only' => true];

        return $types;
    }

    /**
     * @return array<int, string>
     */
    protected function names(?string $class): array
    {
        if (! $class || ! class_exists($class)) {
            return [];
        }

        return $class::query()->orderBy('name')->pluck('name')->unique()->values()->all();
    }

    /**
     * The published form-builder forms a step can use
     *
     * @return array<int, array{key: string, name: string, versions: array<int, int>}>
     */
    protected function forms(): array
    {
        return FormBuilder::model('form')::query()
            ->published()
            ->orderBy('name')
            ->get()
            ->map(fn ($form) => [
                'key' => $form->key,
                'name' => (string) $form->name,
                'versions' => $form->versions()->whereNotNull('published_at')->orderBy('number')->pluck('number')->all(),
            ])
            ->all();
    }

    protected function colorLabel(string $color): string
    {
        return match ($color) {
            'primary' => __('Primary'),
            'secondary' => __('Grey'),
            'success' => __('Green'),
            'info' => __('Blue'),
            'warning' => __('Orange'),
            'danger' => __('Red'),
            default => $color,
        };
    }

    public function subjectLabel(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        $subjects = (array) config('workflows.subjects', []);

        return $subjects[$type] ?? str(class_basename($type))->headline()->toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Instances and tasks
    |--------------------------------------------------------------------------
    */

    /**
     * An instance as the lists show it
     *
     * @return array<string, mixed>
     */
    public function instance(WorkflowInstance $instance, ?string $url = null): array
    {
        $step = $instance->currentStep();
        $task = $instance->isActive() ? $instance->currentTask() : null;

        return [
            'id' => $instance->getKey(),
            'title' => __('Instance :id', ['id' => $instance->getKey()]),
            'workflow' => $instance->workflow?->name,
            'workflow_key' => $instance->workflow?->key,
            'version' => $instance->version?->number,
            'status' => $instance->status,
            'status_label' => Strings::instanceStatus($instance->status),
            'outcome' => $instance->outcome,
            'outcome_label' => $instance->outcome ? $this->outcomeLabel($instance) : null,
            'step' => $instance->current_step,
            'step_name' => $step ? (Workflows::text($step->getName()) ?? $step->getKey()) : null,
            'step_color' => $step?->getColor(),
            'subject' => $this->subject($instance),
            'starter' => $instance->starter ? $this->name($instance->starter) : null,
            'assignees' => $task ? $task->assignees->map->label()->all() : [],
            'due_at' => $task?->due_at?->toIso8601String(),
            'due_at_label' => $task?->due_at ? $this->date($task->due_at) : null,
            'overdue' => (bool) $task?->isOverdue(),
            'started_at' => $instance->started_at?->toIso8601String(),
            'started_at_label' => $instance->started_at ? $this->date($instance->started_at) : null,
            'completed_at_label' => $instance->completed_at ? $this->date($instance->completed_at) : null,
            'url' => $url,
        ];
    }

    protected function outcomeLabel(WorkflowInstance $instance): string
    {
        $step = $instance->definition()->getStep((string) $instance->current_step);

        return Workflows::text($step?->getName()) ?? str((string) $instance->outcome)->headline()->toString();
    }

    /**
     * @return array{type: string, id: mixed, label: string}|null
     */
    public function subject(WorkflowInstance $instance): ?array
    {
        $subject = $instance->subject;

        if (! $subject instanceof Model) {
            return null;
        }

        $label = $subject->getAttribute('name') ?? $subject->getAttribute('title');

        return [
            'type' => $subject->getMorphClass(),
            'id' => $subject->getKey(),
            'label' => $label ? (string) $label : $this->subjectLabel($subject->getMorphClass()) . ' #' . $subject->getKey(),
        ];
    }

    /**
     * The instance's history, as readable lines
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(WorkflowInstance $instance): array
    {
        $definition = $instance->definition();
        $step_name = fn (?string $key) => $key ? (Workflows::text($definition->getStep($key)?->getName()) ?? $key) : null;

        return $instance->history()->with('actor')->get()->map(function (WorkflowHistory $entry) use ($definition, $step_name) {
            $transition = $entry->transition ? $definition->getTransition($entry->transition) : null;
            $action = $entry->action && Workflows::hasAction($entry->action) ? Workflows::action($entry->action)->name() : $entry->action;
            $assignees = implode(', ', array_map(fn ($assignee) => $assignee['label'] ?? '', (array) ($entry->data['assignees'] ?? [])));

            $label = match ($entry->event) {
                WorkflowHistory::STARTED => __('Started'),
                WorkflowHistory::ENTERED => __('Reached :step', ['step' => $step_name($entry->step)]),
                WorkflowHistory::ASSIGNED => __('Assigned to :assignees', ['assignees' => $assignees ?: '—']),
                WorkflowHistory::TRANSITIONED => Workflows::text($transition?->getLabel()) ?? __(':from to :to', ['from' => $step_name($entry->from_step), 'to' => $step_name($entry->step)]),
                WorkflowHistory::ACTION_RAN => __('Ran :action', ['action' => $action]),
                WorkflowHistory::ACTION_FAILED => __(':action failed', ['action' => $action]),
                WorkflowHistory::COMPLETED => __('Finished: :outcome', ['outcome' => $step_name($entry->step)]),
                WorkflowHistory::CANCELLED => __('Cancelled'),
                default => $entry->event,
            };

            return [
                'id' => $entry->getKey(),
                'event' => $entry->event,
                'label' => $label,
                'step' => $entry->step,
                'transition' => $entry->transition,
                'color' => $transition?->getColor() ?? ($entry->event === WorkflowHistory::ACTION_FAILED ? 'danger' : null),
                'actor' => $entry->actor ? $this->name($entry->actor) : null,
                'comment' => $entry->comment,
                'error' => $entry->event === WorkflowHistory::ACTION_FAILED ? ($entry->data['message'] ?? null) : null,
                'at' => $entry->created_at?->toIso8601String(),
                'at_label' => $entry->created_at ? $this->date($entry->created_at) : null,
            ];
        })->all();
    }

    /**
     * The transitions a user can take, for buttons
     *
     * @return array<int, array<string, mixed>>
     */
    public function transitions(WorkflowInstance $instance, ?Model $user): array
    {
        $step = $instance->currentStep();

        return array_map(fn (Transition $transition) => [
            'key' => $transition->getKey(),
            'label' => Workflows::text($transition->getLabel()) ?? str($transition->getKey())->headline()->toString(),
            'color' => $transition->getColor() ?? 'primary',
            'comment' => $transition->getComment(),
            'skips_form' => $transition->skipsForm() || $step?->getForm() === null,
        ], $instance->availableTransitions($user));
    }

    /**
     * A task as the inbox lists it
     *
     * @return array<string, mixed>
     */
    public function task(WorkflowTask $task, ?string $url = null): array
    {
        $instance = $task->instance;

        return [
            ...$this->instance($instance, $url),
            'task_id' => $task->getKey(),
            'created_at_label' => $task->created_at ? $this->date($task->created_at) : null,
            'due_at_label' => $task->due_at ? $this->date($task->due_at) : null,
            'overdue' => $task->isOverdue(),
        ];
    }

    public function name(Model $model): string
    {
        return (string) ($model->getAttribute('name') ?? $model->getAttribute('email') ?? $model->getKey());
    }

    public function date(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->locale(app()->getLocale())->translatedFormat((string) config('workflows.datetime_format', 'j F Y, H:i'));
    }
}
