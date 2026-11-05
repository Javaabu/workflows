<?php

namespace Javaabu\Workflows\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Javaabu\Workflows\Actions\ActionContext;
use Javaabu\Workflows\Actions\ActionRunner;
use Javaabu\Workflows\Definitions\ActionCall;
use Javaabu\Workflows\Facades\Workflows;

/**
 * Runs a queued action (one that implements ShouldQueue) after its transition is saved
 */
class RunWorkflowAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{action: string, settings?: array<string, mixed>}  $call
     */
    public function __construct(
        public int $instance_id,
        public array $call,
        public ?string $step = null,
        public ?string $transition = null,
        public ?string $actor_type = null,
        public int|string|null $actor_id = null,
    ) {}

    public function handle(ActionRunner $runner): void
    {
        $instance = Workflows::model('instance')::query()->find($this->instance_id);

        if (! $instance) {
            return;
        }

        $actor = null;

        if ($this->actor_type && $this->actor_id !== null) {
            $class = Relation::getMorphedModel($this->actor_type) ?? $this->actor_type;
            $actor = class_exists($class) ? $class::query()->find($this->actor_id) : null;
        }

        $call = ActionCall::fromArray($this->call);

        $runner->runNow(Workflows::action($call->getAction()), new ActionContext($instance, $call, $this->step, $this->transition, $actor));
    }
}
