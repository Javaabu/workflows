<?php

namespace Javaabu\Workflows;

use Illuminate\Support\ServiceProvider;
use Javaabu\Permissions\Permissions;
use Javaabu\Workflows\Definitions\DefinitionValidator;
use Javaabu\Workflows\Engine\Engine;
use Javaabu\Workflows\Policies\WorkflowInstancePolicy;
use Javaabu\Workflows\Policies\WorkflowPolicy;

class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');

        $this->app->singleton(WorkflowsManager::class);
        $this->app->alias(WorkflowsManager::class, 'workflows');
        $this->app->singleton(Engine::class);
        $this->app->bind(DefinitionValidator::class);
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');

        $this->registerActions();
        $this->registerPermissions();

        if ($this->app->runningInConsole()) {
            if (WorkflowsManager::$runs_migrations) {
                $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            }

            $this->registerPublishing();
        }
    }

    /**
     * The actions in config('workflows.actions')
     */
    protected function registerActions(): void
    {
        $this->app->make(WorkflowsManager::class)->registerAction((array) config('workflows.actions', []));
    }

    /**
     * The workflow permissions and the default policies, which the app can override
     */
    protected function registerPermissions(): void
    {
        if (config('workflows.register_permissions', true)) {
            Permissions::register('workflows', WorkflowPolicy::permissions(), label: 'Workflows');
        }

        Permissions::policy(config('workflows.models.workflow') ?? Models\Workflow::class, config('workflows.policies.workflow', WorkflowPolicy::class));
        Permissions::policy(config('workflows.models.instance') ?? Models\WorkflowInstance::class, config('workflows.policies.instance', WorkflowInstancePolicy::class));
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'workflows-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'workflows-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/workflows'),
        ], 'workflows-translations');
    }
}
