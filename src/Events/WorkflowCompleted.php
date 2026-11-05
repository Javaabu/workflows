<?php

namespace Javaabu\Workflows\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * An instance reached an end step
 */
class WorkflowCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance, public ?string $outcome = null) {}
}
