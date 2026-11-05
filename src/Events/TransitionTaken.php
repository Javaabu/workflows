<?php

namespace Javaabu\Workflows\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * An instance moved along a transition, by a person or by itself
 */
class TransitionTaken implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance, public string $transition, public string $from, public string $to, public ?Model $actor = null, public ?string $comment = null) {}
}
