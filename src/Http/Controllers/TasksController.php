<?php

namespace Javaabu\Workflows\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Http\Controllers\Concerns\InteractsWithWorkflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Support\Strings;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * People's tasks: what's assigned to them, what they started, and the
 * workflows they can start
 */
class TasksController extends Controller
{
    use InteractsWithWorkflows;

    public const string ASSIGNED = 'assigned';

    public const string STARTED = 'started';

    public function __construct(
        protected BuilderData $data,
    ) {}

    public function index(Request $request): View|Responsable|JsonResponse
    {
        $user = $this->user($request) ?? throw new HttpException(403);
        $view = $request->query('view') === self::STARTED ? self::STARTED : self::ASSIGNED;
        $per_page = (int) config('workflows.per_page', 20);

        if ($view === self::ASSIGNED) {
            $page = Workflows::model('task')::query()
                ->open()
                ->assignedTo($user)
                ->whereHas('instance', fn ($query) => $query->where('status', WorkflowInstance::ACTIVE))
                ->with(['instance.workflow', 'instance.version', 'instance.subject', 'instance.starter', 'assignees'])
                ->orderByRaw('due_at is null')
                ->orderBy('due_at')
                ->latest('id')
                ->paginate($per_page)
                ->withQueryString();

            $items = collect($page->items())->map(fn (WorkflowTask $task) => $this->data->task($task, $this->url($request, 'instances.show', $task->workflow_instance_id)))->all();
        } else {
            $page = Workflows::model('instance')::query()
                ->where('starter_type', $user->getMorphClass())
                ->where('starter_id', $user->getKey())
                ->with(['workflow', 'version', 'subject', 'starter', 'tasks.assignees'])
                ->latest('id')
                ->paginate($per_page)
                ->withQueryString();

            $items = collect($page->items())->map(fn (WorkflowInstance $instance) => $this->data->instance($instance, $this->url($request, 'instances.show', $instance->getKey())))->all();
        }

        $pagination = ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()];

        if ($request->expectsJson()) {
            return new JsonResponse(['data' => $items, 'meta' => $pagination, 'view' => $view]);
        }

        return $this->views()->render('tasks.index', $this->page($request, [
            'items' => $items,
            'view' => $view,
            'pagination' => $pagination,
            'startable' => $this->startable($request),
            'urls' => [
                'assigned' => $this->url($request, 'index'),
                'started' => $this->url($request, 'index', ['view' => self::STARTED]),
            ],
            'strings' => Strings::instances(),
        ]));
    }

    /**
     * Workflows the user can start from here: published, active, and not about a particular record
     *
     * @return array<int, array{key: string, name: string, description: string|null, url: string}>
     */
    protected function startable(Request $request): array
    {
        return Workflows::model('workflow')::query()
            ->published()
            ->where('is_active', true)
            ->whereNull('subject_type')
            ->orderBy('name')
            ->get()
            ->filter(fn (Workflow $workflow) => $this->allowsFor($request, 'start', $workflow))
            ->map(fn (Workflow $workflow) => [
                'key' => $workflow->key,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'url' => $this->url($request, 'start', $workflow->key),
            ])
            ->values()
            ->all();
    }
}
