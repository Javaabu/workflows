<?php

namespace Javaabu\Workflows\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\FormBuilder\Models\FormEntry;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Engine\StepForms;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Http\Controllers\Concerns\InteractsWithWorkflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Support\Strings;

/**
 * A workflow's instances (admin), and one instance: its history, what it
 * collected, and the current task's transitions and form. The instance pages
 * are served by Route::workflows() (`{workflow}/instances/{instance}`) and by
 * Route::workflowTasks() (`{instance}`).
 */
class InstancesController extends Controller
{
    use InteractsWithWorkflows;

    public function __construct(
        protected BuilderData $data,
        protected StepForms $forms,
    ) {}

    public function index(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'viewInstances', $model);

        $status = in_array($request->query('status'), WorkflowInstance::STATUSES, true) ? (string) $request->query('status') : null;

        $instances = $this->query($request, $model)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['workflow', 'version', 'subject', 'starter', 'tasks.assignees'])
            ->latest('id')
            ->paginate((int) config('workflows.per_page', 20))
            ->withQueryString();

        $items = collect($instances->items())
            ->map(fn (WorkflowInstance $instance) => $this->data->instance($instance, $this->url($request, 'instances.show', [$model->key, $instance->getKey()])))
            ->all();

        $pagination = ['current_page' => $instances->currentPage(), 'last_page' => $instances->lastPage(), 'per_page' => $instances->perPage(), 'total' => $instances->total()];

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $items, 'meta' => $pagination]);
        }

        return $this->views()->render('instances.index', $this->page($request, [
            'record' => $this->data->workflow($request, $model),
            'instances' => $items,
            'pagination' => $pagination,
            'filter' => $status,
            'statuses' => array_map(fn (string $value) => ['value' => $value, 'label' => Strings::instanceStatus($value)], WorkflowInstance::STATUSES),
            'urls' => ['index' => $this->url($request, 'instances.index', $model->key), 'back' => $this->url($request, 'show', $model->key)],
            'strings' => Strings::instances(),
        ]));
    }

    /**
     * The instances the admin list shows. Override to scope them.
     */
    protected function query(Request $request, Workflow $workflow)
    {
        return Workflows::model('instance')::query()->where('workflow_id', $workflow->getKey());
    }

    public function show(Request $request): View|Responsable|JsonResponse
    {
        [$instance, $workflow] = $this->resolve($request);

        $this->authorizeFor($request, 'view', $instance);

        $user = $this->user($request);
        $can_act = $user !== null && $this->allowsFor($request, 'act', $instance);
        $step = $instance->currentStep();
        $transitions = $can_act ? $this->data->transitions($instance, $user) : [];

        $form = $can_act && $step?->getForm() && $transitions !== [] ? $this->forms->stencil($step)?->embedded() : null;

        $page = [
            'instance' => $this->data->instance($instance),
            'workflow' => ['key' => $instance->workflow?->key, 'name' => $instance->workflow?->name],
            'history' => $this->data->history($instance),
            'entries' => $this->entries($instance),
            'data' => $this->values($instance),
            'transitions' => $transitions,
            'form' => $form ? ($this->views()->isInertia() || $request->expectsJson() ? $form->render('json') : $form) : null,
            'can' => [
                'act' => $can_act && $transitions !== [],
                'cancel' => $this->allowsFor($request, 'cancel', $instance),
            ],
            'urls' => [
                'transition' => $this->url($request, 'instances.transition', $this->parameters($instance, $workflow)),
                'cancel' => $this->url($request, 'instances.cancel', $this->parameters($instance, $workflow)),
                'back' => $workflow ? $this->url($request, 'instances.index', $workflow->key) : $this->url($request, 'index'),
            ],
            'strings' => Strings::instances(),
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($page);
        }

        return $this->views()->render('instances.show', $this->page($request, $page));
    }

    /**
     * Take one of the current task's transitions: `transition`, `comment`, and the step's form
     */
    public function transition(Request $request): RedirectResponse|JsonResponse
    {
        [$instance, $workflow] = $this->resolve($request);

        $this->authorizeFor($request, 'act', $instance);

        $request->validate([
            'transition' => ['required', 'string'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $key = (string) $request->input('transition');
        $transition = $instance->definition()->getTransition($key);

        $instance = Workflows::transition($instance, $key, by: $this->user($request), data: $request, comment: $request->input('comment'));

        $label = Workflows::text($transition?->getLabel()) ?? $key;
        $url = $this->allowsFor($request, 'view', $instance)
            ? $this->url($request, 'instances.show', $this->parameters($instance, $workflow))
            : ($workflow ? $this->url($request, 'instances.index', $workflow->key) : $this->url($request, 'index'));

        return $this->done($request, __('Done: :transition.', ['transition' => $label]), $url, ['instance' => $this->data->instance($instance)]);
    }

    public function cancel(Request $request): RedirectResponse|JsonResponse
    {
        [$instance, $workflow] = $this->resolve($request);

        $this->authorizeFor($request, 'cancel', $instance);

        $request->validate(['reason' => ['nullable', 'string', 'max:5000']]);

        $instance = Workflows::cancel($instance, by: $this->user($request), reason: $request->input('reason'));

        return $this->done($request, __('Cancelled.'), $this->url($request, 'instances.show', $this->parameters($instance, $workflow)), ['instance' => $this->data->instance($instance)]);
    }

    /**
     * The instance (and, on the admin routes, its workflow) from the route
     *
     * @return array{0: WorkflowInstance, 1: Workflow|null}
     */
    protected function resolve(Request $request): array
    {
        $key = $request->route('workflow');
        $workflow = is_string($key) ? $this->findWorkflow($key) : null;

        return [$this->findInstance((string) $request->route('instance'), $workflow), $workflow];
    }

    /**
     * @return array<int, mixed>
     */
    protected function parameters(WorkflowInstance $instance, ?Workflow $workflow): array
    {
        return $workflow ? [$workflow->key, $instance->getKey()] : [$instance->getKey()];
    }

    /**
     * What the start form and each task's form collected, read-only
     *
     * @return array<int, array{step: string, name: string, display: mixed}>
     */
    protected function entries(WorkflowInstance $instance): array
    {
        $definition = $instance->definition();
        $entries = [];
        $json = $this->views()->isInertia() || request()->expectsJson();

        $start = FormEntry::forSubject($instance)->latest('id')->first();

        if ($start) {
            $step = $definition->getStartStep();
            $entries[] = ['step' => (string) $step?->getKey(), 'name' => Workflows::text($step?->getName()) ?? __('Start'), 'display' => $this->display($start, $json)];
        }

        $tasks = $instance->tasks()->whereNotNull('form_entry_id')->where('status', WorkflowTask::COMPLETED)->with('entry')->get();

        foreach ($tasks as $task) {
            if (! $task->entry) {
                continue;
            }

            $entries[] = [
                'step' => $task->step,
                'name' => Workflows::text($definition->getStep($task->step)?->getName()) ?? $task->step,
                'display' => $this->display($task->entry, $json),
            ];
        }

        return $entries;
    }

    protected function display(FormEntry $entry, bool $json): mixed
    {
        $display = FormBuilder::display($entry);

        return $json ? $display->render('json') : $display;
    }

    /**
     * The instance's data when no form collected it (set by code or actions)
     *
     * @return array<int, array{key: string, value: string}>
     */
    protected function values(WorkflowInstance $instance): array
    {
        if (FormEntry::forSubject($instance)->exists() || $instance->tasks()->whereNotNull('form_entry_id')->exists()) {
            return [];
        }

        $values = [];

        foreach ($instance->data ?? [] as $key => $value) {
            $values[] = ['key' => str((string) $key)->headline()->toString(), 'value' => is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE)];
        }

        return $values;
    }
}
