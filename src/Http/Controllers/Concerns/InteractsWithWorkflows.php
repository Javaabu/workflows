<?php

namespace Javaabu\Workflows\Http\Controllers\Concerns;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Routing\WorkflowRoutes;
use Javaabu\Workflows\Views\ViewRenderer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared by the package's controllers: finding workflows and instances,
 * authorizing for the route's guard, route names, and what every page gets
 */
trait InteractsWithWorkflows
{
    protected function views(): ViewRenderer
    {
        return app(ViewRenderer::class);
    }

    protected function findWorkflow(string $key): Workflow
    {
        /** @var Workflow|null $workflow */
        $workflow = Workflows::model('workflow')::query()->where('key', $key)->first();

        return $workflow ?? throw new NotFoundHttpException;
    }

    protected function findInstance(string|int $id, ?Workflow $workflow = null): WorkflowInstance
    {
        /** @var WorkflowInstance|null $instance */
        $instance = Workflows::model('instance')::query()
            ->when($workflow, fn ($query) => $query->where('workflow_id', $workflow->getKey()))
            ->find($id);

        return $instance ?? throw new NotFoundHttpException;
    }

    protected function authorizeFor(Request $request, string $ability, mixed $argument = []): void
    {
        $this->gate($request)->authorize($ability, $argument);
    }

    protected function allowsFor(Request $request, string $ability, mixed $argument = []): bool
    {
        return $this->gate($request)->allows($ability, $argument);
    }

    protected function gate(Request $request): Gate
    {
        $gate = app(Gate::class);

        $user = $this->user($request);

        return $user ? $gate->forUser($user) : $gate;
    }

    protected function user(Request $request): ?Model
    {
        $user = $request->user(WorkflowRoutes::guard($request));

        return $user instanceof Model ? $user : null;
    }

    protected function routeName(Request $request, string $action): string
    {
        return WorkflowRoutes::namePrefix($request) . ".{$action}";
    }

    /**
     * @param  array<string, mixed>|string|int  $parameters
     */
    protected function url(Request $request, string $action, mixed $parameters = []): string
    {
        return route($this->routeName($request, $action), $parameters);
    }

    protected function hasRoute(Request $request, string $action): bool
    {
        return app('router')->has($this->routeName($request, $action));
    }

    /**
     * What every page gets: the layout, the status message
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function page(Request $request, array $data): array
    {
        return [
            'status' => $request->hasSession() ? $request->session()->get('status') : null,
            'layout' => config('workflows.layout', 'layouts.app'),
            'layout_section' => config('workflows.layout_section', 'content'),
            ...$data,
        ];
    }

    /**
     * Back to a page with a message, or the message as JSON
     *
     * @param  array<string, mixed>  $json
     */
    protected function done(Request $request, string $message, string $url, array $json = [], int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message, ...$json], $status);
        }

        return redirect()->to($url)->with('status', $message);
    }
}
