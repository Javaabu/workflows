<?php

namespace Javaabu\Workflows\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\Workflows\Models\WorkflowTask;

/**
 * A task step was entered and its task assigned
 */
class TaskCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public WorkflowTask $task) {}
}
