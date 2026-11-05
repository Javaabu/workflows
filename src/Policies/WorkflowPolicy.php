<?php

namespace Javaabu\Workflows\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Models\Workflow;

/**
 * Who may build workflows, start them and see their instances
 *
 * | Ability                 | Permission                                          |
 * | ----------------------- | --------------------------------------------------- |
 * | viewAny, view, test     | view_workflows, or any other workflow permission    |
 * | create, update          | edit_workflows (update: not for locked workflows)   |
 * | delete                  | delete_workflows (not locked)                       |
 * | publish                 | publish_workflows (not locked)                      |
 * | start                   | start_workflows, while it's published and active    |
 * | viewInstances           | view_workflow_instances or manage_workflow_instances |
 *
 * manage_workflow_instances also lets someone act on any task and cancel any
 * instance. Override it with `config('workflows.policies.workflow')`,
 * `config('permissions.policies')` or `Gate::policy()`.
 */
class WorkflowPolicy
{
    public const string VIEW = 'view_workflows';

    public const string EDIT = 'edit_workflows';

    public const string DELETE = 'delete_workflows';

    public const string PUBLISH = 'publish_workflows';

    public const string START = 'start_workflows';

    public const string VIEW_INSTANCES = 'view_workflow_instances';

    public const string MANAGE_INSTANCES = 'manage_workflow_instances';

    /**
     * @return array<string, string>
     */
    public static function permissions(): array
    {
        return [
            self::VIEW => 'View workflows',
            self::EDIT => 'Create and edit workflows',
            self::DELETE => 'Delete workflows',
            self::PUBLISH => 'Publish workflows',
            self::START => 'Start workflows',
            self::VIEW_INSTANCES => 'View every workflow instance',
            self::MANAGE_INSTANCES => 'Act on any workflow task and cancel instances',
        ];
    }

    public function viewAny(Model $user): bool
    {
        return $this->allows($user, self::VIEW, self::EDIT, self::DELETE, self::PUBLISH);
    }

    public function view(Model $user, Workflow $workflow): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Run the tester
     */
    public function test(Model $user, Workflow $workflow): bool
    {
        return $this->viewAny($user);
    }

    public function create(Model $user): bool
    {
        return $this->allows($user, self::EDIT);
    }

    public function update(Model $user, Workflow $workflow): bool
    {
        return ! $workflow->is_locked && $this->allows($user, self::EDIT);
    }

    public function delete(Model $user, Workflow $workflow): bool
    {
        return ! $workflow->is_locked && $this->allows($user, self::DELETE);
    }

    public function publish(Model $user, Workflow $workflow): bool
    {
        return ! $workflow->is_locked && $this->allows($user, self::PUBLISH);
    }

    public function start(Model $user, Workflow $workflow): bool
    {
        return $workflow->canStart() && $this->allows($user, self::START);
    }

    public function viewInstances(Model $user, Workflow $workflow): bool
    {
        return $this->allows($user, self::VIEW_INSTANCES, self::MANAGE_INSTANCES);
    }

    protected function allows(Model $user, string ...$permissions): bool
    {
        if (! method_exists($user, 'can')) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
