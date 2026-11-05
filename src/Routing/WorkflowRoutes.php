<?php

namespace Javaabu\Workflows\Routing;

/**
 * Registers the package's routes (Route::workflows(), Route::workflowTasks())
 */
class WorkflowRoutes
{
    /** The name of the route that shows an instance, from the first Route::workflowTasks() */
    protected static ?string $instance_route = null;

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
