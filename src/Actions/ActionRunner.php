<?php

namespace Javaabu\Workflows\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Definitions\ActionCall;
use Javaabu\Workflows\Engine\History;
use Javaabu\Workflows\Events\ActionFailed;
use Javaabu\Workflows\Jobs\RunWorkflowAction;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\WorkflowsManager;
use Throwable;

/**
 * Runs the actions of a step or a transition, records each in the history, and
 * queues the ones that implement ShouldQueue
 */
class ActionRunner
{
    public function __construct(
        protected WorkflowsManager $workflows,
        protected History $history,
    ) {}

    /**
     * @param  array<int, ActionCall>  $calls
     */
    public function run(array $calls, WorkflowInstance $instance, ?string $step = null, ?string $transition = null, ?Model $actor = null): void
    {
        foreach ($calls as $call) {
            $action = $this->workflows->action($call->getAction());

            if ($action instanceof ShouldQueue) {
                RunWorkflowAction::dispatch($instance->getKey(), $call->toArray(), $step, $transition, $actor?->getMorphClass(), $actor?->getKey())->afterCommit();

                continue;
            }

            $this->runNow($action, new ActionContext($instance, $call, $step, $transition, $actor));
        }
    }

    /**
     * Run one action, now. A failure is recorded and reported, then rethrown
     * when workflows.actions_on_failure is `throw` (which undoes the step).
     */
    public function runNow(Action $action, ActionContext $context): void
    {
        try {
            $action->handle($context);
        } catch (Throwable $exception) {
            $this->history->record($context->instance, WorkflowHistory::ACTION_FAILED, [
                'step' => $context->step,
                'transition' => $context->transition,
                'action' => $action::key(),
                'data' => ['message' => $exception->getMessage()],
            ], $context->actor);

            ActionFailed::dispatch($context->instance, $action::key(), $exception);

            if (config('workflows.actions_on_failure', 'continue') === 'throw') {
                throw $exception;
            }

            report($exception);

            return;
        }

        $this->history->record($context->instance, WorkflowHistory::ACTION_RAN, [
            'step' => $context->step,
            'transition' => $context->transition,
            'action' => $action::key(),
        ], $context->actor);
    }
}
