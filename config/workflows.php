<?php

use Javaabu\Workflows\Actions\BuiltIn;
use Javaabu\Workflows\Http\Controllers;
use Javaabu\Workflows\Models;
use Javaabu\Workflows\Policies\WorkflowInstancePolicy;
use Javaabu\Workflows\Policies\WorkflowPolicy;

return [

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    | What workflows can do by themselves when a step is entered or a
    | transition is taken. Add your own (classes extending
    | Javaabu\Workflows\Actions\Action), replace a built-in one with a
    | subclass, or remove one. You can also register them from a service
    | provider with Workflows::registerAction().
    |
    */

    'actions' => [
        BuiltIn\NotifyAction::class,
        BuiltIn\UpdateModelAction::class,
        BuiltIn\SetDataAction::class,
        BuiltIn\WebhookAction::class,
    ],

    /*
    | What happens when an action throws: continue (record it in the history,
    | fire ActionFailed, report the exception and go on) or throw (undo the
    | whole start or transition).
    */

    'actions_on_failure' => 'continue',

    /*
    | The notify action's notification. With javaabu/notifications installed
    | it's a BaseNotification (every channel, the bell, preferences) unless
    | javaabu is false; class replaces it with your own (constructed with a
    | WorkflowMessage and the channels). Webhooks give up after timeout seconds.
    */

    'notifications' => [
        'javaabu' => true,
        'class' => null,
    ],

    'webhooks' => [
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Running
    |--------------------------------------------------------------------------
    |
    | max_automatic_steps stops a workflow that loops through automatic steps.
    | starters_can_cancel lets whoever started an instance cancel it.
    |
    */

    'max_automatic_steps' => 100,

    'starters_can_cancel' => true,

    /*
    |--------------------------------------------------------------------------
    | Seeders
    |--------------------------------------------------------------------------
    |
    | Whether Workflows::sync() publishes the version it makes. Turn it off to
    | review synced workflows in the builder before they go live.
    |
    */

    'sync' => [
        'publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The models and tables. Extend a model to add to it and set it here. A
    | null workflow model is Models\Translatable\Workflow when
    | javaabu/translatable is on, Models\Workflow otherwise.
    |
    */

    'models' => [
        'workflow' => null,
        'version' => Models\WorkflowVersion::class,
        'instance' => Models\WorkflowInstance::class,
        'task' => Models\WorkflowTask::class,
        'assignee' => Models\WorkflowTaskAssignee::class,
        'history' => Models\WorkflowHistory::class,
    ],

    'tables' => [
        'workflows' => 'workflows',
        'workflow_versions' => 'workflow_versions',
        'workflow_instances' => 'workflow_instances',
        'workflow_tasks' => 'workflow_tasks',
        'workflow_task_assignees' => 'workflow_task_assignees',
        'workflow_history' => 'workflow_history',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | The stack the package's pages render with: blade (javaabu/forms
    | components) or inertia (React pages shipped through javaabu/manifest).
    | Requests that expect JSON always get JSON.
    |
    */

    'views' => env('WORKFLOWS_VIEWS', 'blade'),

    /*
    | The Blade layout the pages extend, and the section they fill. per_page is
    | the length of lists; dates show in datetime_format (translated).
    */

    'layout' => 'layouts.app',

    'layout_section' => 'content',

    'per_page' => 20,

    'datetime_format' => 'j F Y, H:i',

    /*
    | The models workflows can be about, for the settings page's subject list:
    | morph alias or class => label. Empty: a text input.
    */

    'subjects' => [],

    /*
    | The route notifications link to for an instance. Null: the first
    | Route::workflowTasks()'s instance page. Workflows::instanceUrlUsing()
    | overrides both.
    */

    'instance_route' => null,

    /*
    |--------------------------------------------------------------------------
    | Controllers and authorization
    |--------------------------------------------------------------------------
    |
    | Replace a controller with a subclass, or pass `controllers` to
    | Route::workflows() and Route::workflowTasks(). The policies are the
    | package defaults; apps can also override them with
    | config('permissions.policies') or Gate::policy(). Turn off
    | register_permissions to register your own.
    |
    */

    'controllers' => [
        'workflows' => Controllers\WorkflowsController::class,
        'tester' => Controllers\TesterController::class,
        'instances' => Controllers\InstancesController::class,
        'tasks' => Controllers\TasksController::class,
        'start' => Controllers\StartController::class,
        'assistant' => Controllers\AssistantController::class,
    ],

    'policies' => [
        'workflow' => WorkflowPolicy::class,
        'instance' => WorkflowInstancePolicy::class,
    ],

    'register_permissions' => true,

    /*
    |--------------------------------------------------------------------------
    | AI assistant
    |--------------------------------------------------------------------------
    |
    | With laravel/ai installed, the builder has an assistant that builds a
    | workflow, or changes one, from a description, as a draft to review.
    | provider and model pick what laravel/ai uses (null: its defaults);
    | attempts is how many answers it gets to produce a valid workflow.
    |
    */

    'assistant' => [
        'enabled' => env('WORKFLOWS_ASSISTANT', true),
        'provider' => env('WORKFLOWS_ASSISTANT_PROVIDER'),
        'model' => env('WORKFLOWS_ASSISTANT_MODEL'),
        'timeout' => 120,
        'attempts' => 2,
        'max_length' => 4000,
    ],

];
