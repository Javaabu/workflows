<?php

namespace Javaabu\Workflows\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Engine\StepForms;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Http\Controllers\Concerns\InteractsWithWorkflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Support\Strings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Starting an instance: the start form (if the start step has one) and the
 * subject, passed as `?subject=<key>` for workflows about a model
 */
class StartController extends Controller
{
    use InteractsWithWorkflows;

    public function __construct(
        protected BuilderData $data,
        protected StepForms $forms,
    ) {}

    public function show(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'start', $model);

        $subject = $this->subject($request, $model);
        $start = $model->definition()?->getStartStep();
        $form = $start ? $this->forms->stencil($start)?->embedded() : null;
        $json = $this->views()->isInertia() || $request->expectsJson();

        $page = [
            'workflow' => ['key' => $model->key, 'name' => $model->name, 'description' => $model->description],
            'subject' => $subject ? ['type' => $subject->getMorphClass(), 'id' => $subject->getKey(), 'label' => (string) ($subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getKey())] : null,
            'form' => $form ? ($json ? $form->render('json') : $form) : null,
            'urls' => [
                'store' => $this->url($request, 'start.store', array_filter([$model->key, 'subject' => $subject?->getKey()])),
                'back' => $this->url($request, 'index'),
            ],
            'strings' => [...Strings::instances(), 'title' => __('Start :workflow', ['workflow' => $model->name])],
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($page);
        }

        return $this->views()->render('start.show', $this->page($request, $page));
    }

    public function store(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'start', $model);

        $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        $start = $model->definition()?->getStartStep();

        $instance = Workflows::start(
            $model,
            subject: $this->subject($request, $model),
            by: $this->user($request),
            data: $start?->getForm() ? $request : [],
            comment: $request->input('comment'),
        );

        $url = $this->allowsFor($request, 'view', $instance)
            ? $this->url($request, 'instances.show', $instance->getKey())
            : $this->url($request, 'index');

        return $this->done($request, __('Started :workflow.', ['workflow' => $model->name]), $url, ['instance' => $this->data->instance($instance)], 201);
    }

    /**
     * The subject from `?subject=`, which the user must be able to see when it has a policy
     */
    protected function subject(Request $request, Workflow $workflow): ?Model
    {
        $class = $workflow->subjectClass();

        if (! $class) {
            return null;
        }

        $key = $request->query('subject', $request->input('subject'));

        if ($key === null || $key === '') {
            throw new NotFoundHttpException;
        }

        $subject = $class::query()->find($key) ?? throw new NotFoundHttpException;

        if (Gate::getPolicyFor($subject)) {
            $this->authorizeFor($request, 'view', $subject);
        }

        return $subject;
    }
}
