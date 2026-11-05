<?php

namespace Javaabu\Workflows\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * An instance entered a step
 */
class StepEntered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance, public string $step, public ?Model $actor = null) {}
}
