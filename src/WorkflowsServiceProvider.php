<?php

namespace Javaabu\Workflows;

use Illuminate\Support\ServiceProvider;

class WorkflowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
            ], 'workflows-config');

            $this->publishes([
                __DIR__ . '/../lang' => lang_path('vendor/workflows'),
            ], 'workflows-translations');
        }
    }
}
