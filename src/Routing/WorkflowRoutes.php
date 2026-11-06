<?php

namespace Javaabu\Workflows\Routing;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Javaabu\Workflows\Ai\WorkflowAssistant;
use Javaabu\Workflows\Http\Controllers\AssistantController;
use Javaabu\Workflows\Http\Controllers\InstancesController;
use Javaabu\Workflows\Http\Controllers\StartController;
use Javaabu\Workflows\Http\Controllers\TasksController;
use Javaabu\Workflows\Http\Controllers\TesterController;
use Javaabu\Workflows\Http\Controllers\WorkflowsController;

/**
 * Registers the routes of `Route::workflows()` (the admin side: workflows, the
 * builder, the tester, instances) and `Route::workflowTasks()` (people's tasks,
 * starting workflows, acting on instances)
 *
 * ```php
 * Route::workflows(['guard' => 'web_admin', 'prefix' => 'admin/workflows', 'name' => 'admin.workflows']);
 * Route::workflowTasks();   // /tasks, for the default guard
 * ```
 */
class WorkflowRoutes
{
    /** The route action key that holds the guard the routes were registered for */
    public const string GUARD_ACTION = 'workflows_guard';

    /** The name of the route that shows an instance, from the first Route::workflowTasks() */
    protected static ?string $instance_route = null;

    /**
     * @param  array{guard?: string|null, prefix?: string, name?: string, middleware?: string|array<int, string>|null, controllers?: array<string, class-string>}  $options
     */
    public static function admin(Router $router, array $options = []): void
    {
        $guard = $options['guard'] ?? null;
        $prefix = trim($options['prefix'] ?? 'workflows', '/');
        $name = rtrim($options['name'] ?? 'workflows', '.');
        $middleware = $options['middleware'] ?? [$guard ? "auth:{$guard}" : 'auth'];
        $controllers = [...(array) config('workflows.controllers', []), ...($options['controllers'] ?? [])];

        $workflows = $controllers['workflows'] ?? WorkflowsController::class;
        $tester = $controllers['tester'] ?? TesterController::class;
        $instances = $controllers['instances'] ?? InstancesController::class;
        $assistant = $controllers['assistant'] ?? AssistantController::class;

        $router->group([
            'prefix' => $prefix,
            'as' => "{$name}.",
            'middleware' => $middleware,
            self::GUARD_ACTION => $guard,
        ], function (Router $router) use ($workflows, $tester, $instances, $assistant) {
            $key = '[A-Za-z0-9_-]+';

            $router->get('/', [$workflows, 'index'])->name('index');
            $router->get('/create', [$workflows, 'create'])->name('create');
            $router->post('/', [$workflows, 'store'])->name('store');

            if (WorkflowAssistant::available()) {
                $router->post('/assistant', [$assistant, 'create'])->name('assistant.create');
                $router->post('/{workflow}/assistant', [$assistant, 'update'])->name('assistant')->where('workflow', $key);
            }

            $router->get('/{workflow}', [$workflows, 'show'])->name('show')->where('workflow', $key);
            $router->get('/{workflow}/edit', [$workflows, 'edit'])->name('edit')->where('workflow', $key);
            $router->patch('/{workflow}', [$workflows, 'update'])->name('update')->where('workflow', $key);
            $router->delete('/{workflow}', [$workflows, 'destroy'])->name('destroy')->where('workflow', $key);
            $router->put('/{workflow}/definition', [$workflows, 'definition'])->name('definition')->where('workflow', $key);
            $router->post('/{workflow}/publish', [$workflows, 'publish'])->name('publish')->where('workflow', $key);
            $router->delete('/{workflow}/draft', [$workflows, 'discardDraft'])->name('draft.destroy')->where('workflow', $key);
            $router->get('/{workflow}/versions', [$workflows, 'versions'])->name('versions')->where('workflow', $key);
            $router->get('/{workflow}/export', [$workflows, 'export'])->name('export')->where('workflow', $key);

            $router->get('/{workflow}/test', [$tester, 'show'])->name('test')->where('workflow', $key);
            $router->post('/{workflow}/test', [$tester, 'run'])->name('test.run')->where('workflow', $key);

            $router->get('/{workflow}/instances', [$instances, 'index'])->name('instances.index')->where('workflow', $key);
            $router->get('/{workflow}/instances/{instance}', [$instances, 'show'])->name('instances.show')->where(['workflow' => $key, 'instance' => '[0-9]+']);
            $router->post('/{workflow}/instances/{instance}/transition', [$instances, 'transition'])->name('instances.transition')->where(['workflow' => $key, 'instance' => '[0-9]+']);
            $router->post('/{workflow}/instances/{instance}/cancel', [$instances, 'cancel'])->name('instances.cancel')->where(['workflow' => $key, 'instance' => '[0-9]+']);
        });
    }

    /**
     * @param  array{guard?: string|null, prefix?: string, name?: string, middleware?: string|array<int, string>|null, controllers?: array<string, class-string>}  $options
     */
    public static function tasks(Router $router, array $options = []): void
    {
        $guard = $options['guard'] ?? null;
        $prefix = trim($options['prefix'] ?? 'tasks', '/');
        $name = rtrim($options['name'] ?? 'workflow-tasks', '.');
        $middleware = $options['middleware'] ?? [$guard ? "auth:{$guard}" : 'auth'];
        $controllers = [...(array) config('workflows.controllers', []), ...($options['controllers'] ?? [])];

        $tasks = $controllers['tasks'] ?? TasksController::class;
        $start = $controllers['start'] ?? StartController::class;
        $instances = $controllers['instances'] ?? InstancesController::class;

        $router->group([
            'prefix' => $prefix,
            'as' => "{$name}.",
            'middleware' => $middleware,
            self::GUARD_ACTION => $guard,
        ], function (Router $router) use ($tasks, $start, $instances) {
            $router->get('/', [$tasks, 'index'])->name('index');
            $router->get('/start/{workflow}', [$start, 'show'])->name('start')->where('workflow', '[A-Za-z0-9_-]+');
            $router->post('/start/{workflow}', [$start, 'store'])->name('start.store')->where('workflow', '[A-Za-z0-9_-]+');
            $router->get('/{instance}', [$instances, 'show'])->name('instances.show')->where('instance', '[0-9]+');
            $router->post('/{instance}/transition', [$instances, 'transition'])->name('instances.transition')->where('instance', '[0-9]+');
            $router->post('/{instance}/cancel', [$instances, 'cancel'])->name('instances.cancel')->where('instance', '[0-9]+');
        });

        static::$instance_route ??= "{$name}.instances.show";
    }

    /**
     * The guard the current route was registered for, or null for the default guard
     */
    public static function guard(Request $request): ?string
    {
        return $request->route()?->getAction(self::GUARD_ACTION);
    }

    /**
     * The route name prefix of the current route, e.g. `admin.workflows`
     */
    public static function namePrefix(Request $request, string $fallback = 'workflows'): string
    {
        $name = (string) $request->route()?->getName();

        foreach (['.instances.', '.draft.', '.test.', '.start.', '.assistant.'] as $part) {
            if (str_contains($name, $part)) {
                return substr($name, 0, strpos($name, $part));
            }
        }

        return str_contains($name, '.')
            ? substr($name, 0, strrpos($name, '.'))
            : $fallback;
    }

    public static function instanceRoute(): ?string
    {
        return static::$instance_route;
    }

    /**
     * @internal for tests
     */
    public static function flush(): void
    {
        static::$instance_route = null;
    }
}
