<?php

namespace Javaabu\Workflows\Tests\Http;

use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\Routing\WorkflowRoutes;
use Javaabu\Workflows\Tests\TestSupport\Models\Admin;
use Javaabu\Workflows\Tests\TestSupport\Models\User;

trait InteractsWithWorkflows
{
    protected function defineRoutes($router): void
    {
        WorkflowRoutes::flush();

        $router->middleware('web')->group(function ($router) {
            $router->workflows();
            $router->workflows(['guard' => 'web_admin', 'prefix' => 'admin/workflows', 'name' => 'admin.workflows']);
            $router->workflowTasks();
            $router->workflowTasks(['guard' => 'web_admin', 'prefix' => 'admin/tasks', 'name' => 'admin.workflow-tasks']);
        });
    }

    protected function setUpWorkflows(): void
    {
        Manifest::fake();

        $this->artisan('permissions:sync');
    }

    /**
     * Someone with every workflow permission
     *
     * @param  array<int, string>|null  $permissions
     */
    protected function editor(?array $permissions = null): User
    {
        return $this->makeUser('އިދާރާ', $permissions ?? array_keys(WorkflowPolicy::permissions()));
    }

    /**
     * @param  array<int, string>|null  $permissions
     */
    protected function adminEditor(?array $permissions = null): Admin
    {
        return $this->makeAdmin('Aisha', $permissions ?? array_keys(WorkflowPolicy::permissions()));
    }
}
