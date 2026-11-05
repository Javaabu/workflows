<?php

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

    'actions' => [],

    /*
    | What happens when an action throws: continue (record it in the history,
    | fire ActionFailed, report the exception and go on) or throw (undo the
    | whole start or transition).
    */

    'actions_on_failure' => 'continue',

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
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | The policies are the package defaults; apps can also override them with
    | config('permissions.policies') or Gate::policy(). Turn off
    | register_permissions to register your own.
    |
    */

    'policies' => [
        'workflow' => WorkflowPolicy::class,
        'instance' => WorkflowInstancePolicy::class,
    ],

    'register_permissions' => true,

];
