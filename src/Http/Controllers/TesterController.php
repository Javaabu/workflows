<?php

namespace Javaabu\Workflows\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Javaabu\Workflows\Builder\BuilderData;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Engine\Simulation;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Http\Controllers\Concerns\InteractsWithWorkflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Support\Strings;

/**
 * The workflow tester: sample data and choices in, the path a run takes out
 */
class TesterController extends Controller
{
    use InteractsWithWorkflows;

    public function __construct(
        protected BuilderData $data,
    ) {}

    public function show(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'test', $model);

        return $this->render($request, $model, null, [
            'data' => '{}',
            'subject' => '{}',
            'choices' => [],
            'draft' => $model->hasDraft(),
        ]);
    }

    public function run(Request $request, string $workflow): View|Responsable|JsonResponse
    {
        $model = $this->findWorkflow($workflow);

        $this->authorizeFor($request, 'test', $model);

        $validated = $request->validate([
            'data' => ['nullable'],
            'subject' => ['nullable'],
            'choices' => ['nullable', 'array'],
            'choices.*' => ['nullable', 'string'],
            'draft' => ['nullable', 'boolean'],
        ]);

        $data = $this->json($validated['data'] ?? null, 'data');
        $subject = $this->json($validated['subject'] ?? null, 'subject');
        $choices = array_filter((array) ($validated['choices'] ?? []), fn ($choice) => is_string($choice) && $choice !== '');
        $draft = $request->boolean('draft');

        $simulation = Workflows::simulate($model, data: $data, choices: $choices, subject: $subject ?: null, starter: $this->user($request), draft: $draft);

        if ($request->expectsJson()) {
            return new JsonResponse(['simulation' => $simulation->toArray()]);
        }

        return $this->render($request, $model, $simulation, [
            'data' => $this->pretty($data),
            'subject' => $this->pretty($subject),
            'choices' => $choices,
            'draft' => $draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function render(Request $request, Workflow $model, ?Simulation $simulation, array $input): View|Responsable|JsonResponse
    {
        $definition = $model->definition(draft: (bool) $input['draft']);

        $tasks = [];

        foreach ($definition?->getSteps() ?? [] as $step) {
            if (! $step->isTask()) {
                continue;
            }

            $tasks[] = [
                'key' => $step->getKey(),
                'name' => Workflows::text($step->getName()) ?? $step->getKey(),
                'transitions' => array_map(fn (Transition $transition) => [
                    'key' => $transition->getKey(),
                    'label' => Workflows::text($transition->getLabel()) ?? $transition->getKey(),
                ], $definition->transitionsFrom($step->getKey(), defaults_last: false)),
            ];
        }

        $page = [
            'workflow' => $this->data->workflow($request, $model),
            'definition' => $definition?->structure(),
            'catalog' => $this->data->catalog(),
            'tasks' => $tasks,
            'input' => $input,
            'simulation' => $simulation?->toArray(),
            'has_draft' => $model->hasDraft(),
            'urls' => [
                'run' => $this->url($request, 'test.run', $model->key),
                'back' => $this->url($request, 'show', $model->key),
            ],
            'strings' => Strings::tester(),
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($page);
        }

        return $this->views()->render('workflows.test', $this->page($request, $page));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(mixed $value, string $field): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => __('This isn\'t valid JSON.')]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function pretty(array $value): string
    {
        return $value === [] ? '{}' : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
