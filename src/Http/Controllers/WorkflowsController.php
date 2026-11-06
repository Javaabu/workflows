<?php

namespace Javaabu\Workflows\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Builder\WorkflowForms;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Http\Controllers\Concerns\InteractsWithWorkflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Support\Strings;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The workflows: the list, settings, the builder (saving the draft's
 * definition), publishing, versions, export and deleting
 */
class WorkflowsController extends Controller
{
    use InteractsWithWorkflows;

    public function __construct(
        protected BuilderData $data,
        protected WorkflowForms $forms,
    ) {}

    public function index(Request $request): View|Responsable|JsonResponse
    {
        $this->authorizeFor($request, 'viewAny', Workflows::model('workflow'));

        $search = trim((string) $request->query('search', ''));

        $workflows = Workflows::model('workflow')::query()
            ->with(['publishedVersion', 'draftVersion'])
            ->withCount(['instances', 'instances as active_count' => fn ($query) => $query->where('status', 'active')])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('key', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate((int) config('workflows.per_page', 20))
            ->withQueryString();

        $items = collect($workflows->items())->map(fn (Workflow $workflow) => $this->data->workflow($request, $workflow))->all();
        $pagination = ['current_page' => $workflows->currentPage(), 'last_page' => $workflows->lastPage(), 'per_page' => $workflows->perPage(), 'total' => $workflows->total()];

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $items, 'meta' => $pagination]);
        }

        $can_create = $this->allowsFor($request, 'create', Workflows::model('workflow'));

        return $this->views()->render('workflows.index', $this->page($request, [
            'workflows' => $items,
            'pagination' => $pagination,
            'search' => $search,
            'can' => ['create' => $can_create],
            'urls' => array_filter([
                'index' => $this->url($request, 'index'),
                'create' => $this->url($request, 'create'),
                'assistant' => $can_create && $this->hasRoute($request, 'assistant.create') ? $this->url($request, 'assistant.create') : null,
            ]),
            'strings' => Strings::workflows(),
        ]));
    }

    public function create(Request $request): View|Responsable|JsonResponse
    {
        $this->authorizeFor($request, 'create', Workflows::model('workflow'));

        $form = $this->forms->settingsForm()
            ->action($this->url($request, 'store'))
            ->method('POST')
            ->submit(__('Create workflow'));

        if ($request->expectsJson()) {
            return new JsonResponse(['form' => $form->render('json')]);
        }

        return $this->views()->render('workflows.edit', $this->page($request, [
            'form' => $this->views()->isInertia() ? $form->render('json') : $form,
            'record' => null,
            'title' => __('New workflow'),
            'back_url' => $this->url($request, 'index'),
            'strings' => Strings::workflows(),
        ]));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeFor($request, 'create', Workflows::model('workflow'));

        $data = $this->forms->settingsForm()->validate($request);

        $workflow = DB::transaction(function () use ($request, $data) {
            /** @var Workflow $workflow */
            $workflow = Workflows::model('workflow')::make();

            $this->forms->fill($workflow, $data);
            $workflow->save();
            $this->forms->saveTranslations($workflow, $data);

            $workflow->ensureDraft($this->user($request))->replaceStructure($this->starter($workflow->key), validate: false);

            return $workflow;
        });

        return $this->done($request, __('Workflow created.'), $this->url($request, 'show', $workflow->key), ['workflow' => $this->data->workflow($request, $workflow)], 201);
    }

    /**
     * What a new workflow starts with: a start, a task and an end, connected
     */
    protected function starter(string $key): WorkflowDefinition
    {
        return WorkflowDefinition::make($key)
            ->steps([
                Step::start()->name(__('Start'))->position(40, 160),
                Step::task('review')->name(__('Review'))->position(300, 160),
                Step::end('done')->name(__('Done'))->color('success')->position(560, 160),
            ])
            ->transitions([
                Transition::make('submit')->from('start')->to('review'),
                Transition::make('complete')->from('review')->to('done')->label(__('Complete'))->color('success'),
            ]);
    }

    /**
     * The builder
     */
    public function show(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'view', $model);

        $editable = $this->allowsFor($request, 'update', $model);

        $page = [
            'workflow' => $this->data->workflow($request, $model),
            'builder' => $this->data->builder($model, $editable),
            'can' => [
                'update' => $editable,
                'publish' => $this->allowsFor($request, 'publish', $model),
                'delete' => $this->allowsFor($request, 'delete', $model),
                'view_instances' => $this->allowsFor($request, 'viewInstances', $model),
            ],
            'urls' => array_filter([
                'index' => $this->url($request, 'index'),
                'edit' => $this->url($request, 'edit', $model->key),
                'save' => $this->url($request, 'definition', $model->key),
                'publish' => $this->url($request, 'publish', $model->key),
                'discard' => $model->hasDraft() && $model->isPublished() ? $this->url($request, 'draft.destroy', $model->key) : null,
                'test' => $this->url($request, 'test', $model->key),
                'versions' => $this->url($request, 'versions', $model->key),
                'instances' => $this->url($request, 'instances.index', $model->key),
                'export' => $this->url($request, 'export', $model->key),
                'destroy' => $this->url($request, 'destroy', $model->key),
                'assistant' => $editable && $this->hasRoute($request, 'assistant') ? $this->url($request, 'assistant', $model->key) : null,
            ]),
            'strings' => Strings::builder(),
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($page);
        }

        return $this->views()->render('workflows.show', $this->page($request, $page));
    }

    public function edit(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'update', $model);

        $form = $this->forms->settingsForm($model)
            ->action($this->url($request, 'update', $model->key))
            ->method('PATCH')
            ->submit(__('Save'));

        if ($request->expectsJson()) {
            return new JsonResponse(['form' => $form->render('json')]);
        }

        return $this->views()->render('workflows.edit', $this->page($request, [
            'form' => $this->views()->isInertia() ? $form->render('json') : $form,
            'record' => $this->data->workflow($request, $model),
            'title' => __('Settings'),
            'back_url' => $this->url($request, 'show', $model->key),
            'strings' => Strings::workflows(),
        ]));
    }

    public function update(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'update', $model);

        $data = $this->forms->settingsForm($model)->validate($request);

        DB::transaction(function () use ($model, $data) {
            $this->forms->fill($model, $data);
            $model->save();
            $this->forms->saveTranslations($model, $data);
        });

        return $this->done($request, __('Settings saved.'), $this->url($request, 'show', $model->key), ['workflow' => $this->data->workflow($request, $model->refresh())]);
    }

    public function destroy(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'delete', $model);

        $model->delete();

        return $this->done($request, __('Workflow deleted.'), $this->url($request, 'index'));
    }

    /**
     * Save the draft's steps and transitions from the builder. Drafts may be
     * incomplete: the response lists the problems, which block publishing.
     */
    public function definition(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'update', $model);

        $input = $request->input('definition');

        if (is_string($input)) {
            $input = json_decode($input, true);
        }

        if (! is_array($input) || ! is_array($input['steps'] ?? null) || ! is_array($input['transitions'] ?? [])) {
            throw ValidationException::withMessages(['definition' => __('The definition needs a list of steps and transitions.')]);
        }

        $definition = WorkflowDefinition::fromArray($input, $model->key);

        $model->draft($this->user($request))->replaceStructure($definition, validate: false);

        $problems = $this->data->problems($definition);

        return $this->done($request, __('Draft saved.'), $this->url($request, 'show', $model->key), [
            ...$problems,
            'definition' => $definition->structure(),
            'workflow' => $this->data->workflow($request, $model->refresh()),
        ]);
    }

    public function publish(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'publish', $model);

        $version = $model->publish($this->user($request));

        return $this->done($request, __('Version :number published.', ['number' => $version->number]), $this->url($request, 'show', $model->key), ['workflow' => $this->data->workflow($request, $model->refresh())]);
    }

    public function discardDraft(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'update', $model);

        $model->discardDraft();

        return $this->done($request, __('Draft discarded.'), $this->url($request, 'show', $model->key));
    }

    public function versions(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'view', $model);

        $versions = $model->versions()->reorder()->latest('number')->with('author')->get()->map(fn ($version) => $this->data->version($version, $model))->all();

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $versions]);
        }

        return $this->views()->render('workflows.versions', $this->page($request, [
            'record' => $this->data->workflow($request, $model),
            'versions' => $versions,
            'back_url' => $this->url($request, 'show', $model->key),
            'strings' => Strings::workflows(),
        ]));
    }

    /**
     * The workflow as JSON (the definition format), to keep or seed elsewhere
     */
    public function export(Request $request, string $workflow): StreamedResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'view', $model);

        $json = (string) json_encode($model->definition(draft: $request->boolean('draft'))?->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(fn () => print ($json), "{$model->key}.json", ['Content-Type' => 'application/json']);
    }
}
