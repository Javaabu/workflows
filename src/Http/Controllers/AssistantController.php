<?php

namespace Javaabu\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\Workflows\Ai\WorkflowAssistant;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Http\Controllers\Concerns\InteractsWithWorkflows;

/**
 * The AI assistant (with laravel/ai): a new workflow, or a change to a
 * workflow's draft, from a description
 */
class AssistantController extends Controller
{
    use InteractsWithWorkflows;

    public function __construct(
        protected WorkflowAssistant $assistant,
        protected BuilderData $data,
    ) {}

    public function create(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeFor($request, 'create', Workflows::model('workflow'));

        $description = $this->description($request);

        ['workflow' => $workflow, 'summary' => $summary] = $this->assistant->build($description, author: $this->user($request));

        return $this->done($request, $summary, $this->url($request, 'show', $workflow->key), ['workflow' => $this->data->workflow($request, $workflow)], 201);
    }

    public function update(Request $request, string $workflow): RedirectResponse|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'update', $model);

        $description = $this->description($request);

        ['workflow' => $model, 'summary' => $summary] = $this->assistant->build($description, $model, $this->user($request));

        return $this->done($request, $summary, $this->url($request, 'show', $model->key), [
            'workflow' => $this->data->workflow($request, $model),
            'builder' => $this->data->builder($model),
        ]);
    }

    protected function description(Request $request): string
    {
        return (string) $request->validate([
            'description' => ['required', 'string', 'max:' . (int) config('workflows.assistant.max_length', 4000)],
        ])['description'];
    }
}
