<?php

namespace Javaabu\Workflows\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * Who may see an instance, act on it and cancel it
 *
 * | Ability | Who                                                                          |
 * | ------- | ---------------------------------------------------------------------------- |
 * | view    | its starter, anyone assigned to one of its tasks, view/manage_workflow_instances |
 * | act     | an assignee of its open task, or manage_workflow_instances                   |
 * | cancel  | manage_workflow_instances, or its starter when workflows.starters_can_cancel |
 */
class WorkflowInstancePolicy
{
    public function view(Model $user, WorkflowInstance $instance): bool
    {
        if ($this->isStarter($user, $instance) || $this->can($user, WorkflowPolicy::VIEW_INSTANCES, WorkflowPolicy::MANAGE_INSTANCES)) {
            return true;
        }

        return $instance->tasks()->assignedTo($user)->exists();
    }

    public function act(Model $user, WorkflowInstance $instance): bool
    {
        $task = $instance->currentTask();

        return $task !== null && Workflows::engine()->canAct($task, $user);
    }

    public function cancel(Model $user, WorkflowInstance $instance): bool
    {
        if (! $instance->isActive()) {
            return false;
        }

        if ($this->can($user, WorkflowPolicy::MANAGE_INSTANCES)) {
            return true;
        }

        return config('workflows.starters_can_cancel', true) && $this->isStarter($user, $instance);
    }

    protected function isStarter(Model $user, WorkflowInstance $instance): bool
    {
        return $instance->starter_type === $user->getMorphClass() && (string) $instance->starter_id === (string) $user->getKey();
    }

    protected function can(Model $user, string ...$permissions): bool
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
