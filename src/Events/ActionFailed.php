<?php

namespace Javaabu\Workflows\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\Workflows\Models\WorkflowInstance;
use Throwable;

/**
 * An action threw; the instance went on (workflows.actions.on_failure is continue)
 */
class ActionFailed
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance, public string $action, public Throwable $exception) {}
}
