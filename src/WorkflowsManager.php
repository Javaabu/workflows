<?php

namespace Javaabu\Workflows;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Definitions\DefinitionValidator;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Engine\Engine;
use Javaabu\Workflows\Exceptions\WorkflowException;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Models\WorkflowTaskAssignee;
use Javaabu\Workflows\Models\WorkflowVersion;
use Javaabu\Workflows\Support\Text;
use Javaabu\Workflows\Support\Translations;

/**
 * The package's registry and entry point, behind the Workflows facade: models,
 * actions, assignee types, defining and syncing workflows, and running instances.
 */
class WorkflowsManager
{
    /** Set to false (in a service provider's register()) to publish and run the migrations yourself */
    public static bool $runs_migrations = true;

    /** @var array<string, class-string<Model>> */
    public const array MODELS = [
        'workflow' => Workflow::class,
        'version' => WorkflowVersion::class,
        'instance' => WorkflowInstance::class,
        'task' => WorkflowTask::class,
        'assignee' => WorkflowTaskAssignee::class,
        'history' => WorkflowHistory::class,
    ];

    /** @var array<string, Action|class-string<Action>> */
    protected array $actions = [];

    /** @var array<string, array{resolver: callable|string, label: string|null}> */
    protected array $assignees = [];

    /** @var class-string<Workflow>|null */
    protected ?string $translatable_workflow = null;

    public static function ignoreMigrations(): void
    {
        static::$runs_migrations = false;
    }

    /**
     * A model class by name (workflow, version, instance, task, assignee, history), from config('workflows.models')
     *
     * @return class-string<Model>
     */
    public function model(string $name): string
    {
        $model = config("workflows.models.{$name}");

        if ($model) {
            return $model;
        }

        if ($name === 'workflow') {
            return $this->translatable_workflow ??= Translations::enabled() ? Models\Translatable\Workflow::class : Workflow::class;
        }

        return self::MODELS[$name];
    }

    /**
     * @internal forget the workflow model chosen, after javaabu/translatable's languages change
     */
    public function forgetModels(): void
    {
        $this->translatable_workflow = null;
    }

    public function engine(): Engine
    {
        return app(Engine::class);
    }

    public function validator(): DefinitionValidator
    {
        return app(DefinitionValidator::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * Add actions, or replace the ones with the same key
     *
     * @param  Action|class-string<Action>|array<int, Action|class-string<Action>>  $actions
     */
    public function registerAction(Action|string|array $actions): static
    {
        foreach (is_array($actions) ? $actions : [$actions] as $action) {
            $this->actions[$action::key()] = $action;
        }

        return $this;
    }

    public function forgetAction(string $key): static
    {
        unset($this->actions[$key]);

        return $this;
    }

    public function hasAction(string $key): bool
    {
        return isset($this->actions[$key]);
    }

    public function action(string $key): Action
    {
        $action = $this->actions[$key] ?? throw WorkflowException::unknownAction($key);

        if (is_string($action)) {
            $action = $this->actions[$key] = app($action);
        }

        return $action;
    }

    /**
     * @return array<string, Action>
     */
    public function actions(): array
    {
        $actions = [];

        foreach (array_keys($this->actions) as $key) {
            $actions[$key] = $this->action($key);
        }

        return $actions;
    }

    /*
    |--------------------------------------------------------------------------
    | Assignee types
    |--------------------------------------------------------------------------
    */

    /**
     * Add an assignee type. The resolver gets the instance and the assignee's
     * value, and returns users, or Assignee definitions:
     *
     * ```php
     * Workflows::assignee('department_head', fn (WorkflowInstance $instance) => $instance->subject->department->head, __('Department head'));
     * ```
     *
     * @param  callable|class-string  $resolver  a callable, or an invokable class
     */
    public function assignee(string $type, callable|string $resolver, ?string $label = null): static
    {
        $this->assignees[$type] = ['resolver' => $resolver, 'label' => $label];

        return $this;
    }

    public function hasAssigneeResolver(string $type): bool
    {
        return isset($this->assignees[$type]);
    }

    public function resolveAssignee(string $type, WorkflowInstance $instance, ?string $value = null): mixed
    {
        $resolver = $this->assignees[$type]['resolver'] ?? throw WorkflowException::unknownAssignee($type);

        if (is_string($resolver) && ! is_callable($resolver)) {
            $resolver = app($resolver);
        }

        return $resolver instanceof Closure || is_callable($resolver) ? $resolver($instance, $value) : null;
    }

    /**
     * The assignee types apps added, type => label
     *
     * @return array<string, string>
     */
    public function customAssignees(): array
    {
        return array_map(fn (array $assignee) => $assignee['label'] ?? '', $this->assignees);
    }

    /*
    |--------------------------------------------------------------------------
    | Defining workflows
    |--------------------------------------------------------------------------
    */

    /**
     * Start a definition, for a seeder: Workflows::define('leave-request')->…->sync()
     */
    public function define(string $key): WorkflowDefinition
    {
        return WorkflowDefinition::make($key);
    }

    /**
     * Save a definition: create the workflow, or give it a new version when its
     * steps or transitions changed. Publishes the new version unless $publish is
     * false (config('workflows.sync.publish') by default). Running it again with
     * the same definition changes nothing.
     */
    public function sync(WorkflowDefinition $definition, ?bool $publish = null): Workflow
    {
        $publish ??= (bool) config('workflows.sync.publish', true);

        $this->validator()->validate($definition);

        return DB::transaction(function () use ($definition, $publish) {
            /** @var Workflow $workflow */
            $workflow = $this->model('workflow')::query()->withTrashed()->firstOrNew(['key' => $definition->getKey()]);

            $workflow->fill([
                'name' => $this->plainText($definition->getName()) ?? $definition->getKey(),
                'description' => $this->plainText($definition->getDescription()),
                'subject_type' => $definition->getSubject(),
                'is_locked' => $definition->isLocked(),
            ]);

            if ($workflow->trashed()) {
                $workflow->restore();
            }

            $workflow->save();

            Translations::saveModelText($workflow, [
                'name' => $definition->getName(),
                'description' => $definition->getDescription(),
            ]);

            $hash = WorkflowVersion::hashOf($definition);
            $latest = $workflow->versions()->reorder()->latest('number')->first();

            if ($latest?->hash !== $hash) {
                $workflow->ensureDraft()->replaceStructure($definition, validate: false);
            }

            if ($publish && $workflow->draftVersion()->exists()) {
                $workflow->publish();
            }

            return $workflow->refresh();
        });
    }

    /**
     * The workflow with the key
     */
    public function workflow(string $key): Workflow
    {
        return $this->model('workflow')::query()->where('key', $key)->first()
            ?? throw WorkflowException::workflowNotFound($key);
    }

    /*
    |--------------------------------------------------------------------------
    | Running workflows
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Request|array<string, mixed>  $data
     */
    public function start(Workflow|string $workflow, ?Model $subject = null, ?Model $by = null, Request|array $data = [], ?string $comment = null): WorkflowInstance
    {
        return $this->engine()->start($workflow, $subject, $by, $data, $comment);
    }

    /**
     * @param  Request|array<string, mixed>  $data
     */
    public function transition(WorkflowInstance $instance, string $transition, ?Model $by = null, Request|array $data = [], ?string $comment = null, bool $force = false): WorkflowInstance
    {
        return $this->engine()->transition($instance, $transition, $by, $data, $comment, $force);
    }

    public function cancel(WorkflowInstance $instance, ?Model $by = null, ?string $reason = null): WorkflowInstance
    {
        return $this->engine()->cancel($instance, $by, $reason);
    }

    /**
     * Text from a definition (a string or a locale map) in the current locale
     *
     * @param  string|array<string, string>|null  $text
     */
    public function text(string|array|null $text, ?string $locale = null): ?string
    {
        return Text::resolve($text, $locale);
    }

    /**
     * @param  string|array<string, string>|null  $text
     */
    protected function plainText(string|array|null $text): ?string
    {
        if (! is_array($text)) {
            return $text;
        }

        return $text[Translations::defaultLocale()] ?? (reset($text) ?: null);
    }
}
