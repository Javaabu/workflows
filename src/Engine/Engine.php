<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Javaabu\FormBuilder\Models\FormEntry;
use Javaabu\Workflows\Actions\ActionRunner;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Events\StepEntered;
use Javaabu\Workflows\Events\TaskCreated;
use Javaabu\Workflows\Events\TransitionTaken;
use Javaabu\Workflows\Events\WorkflowCancelled;
use Javaabu\Workflows\Events\WorkflowCompleted;
use Javaabu\Workflows\Events\WorkflowStarted;
use Javaabu\Workflows\Exceptions\WorkflowException;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Policies\WorkflowPolicy;
use Javaabu\Workflows\WorkflowsManager;

/**
 * Runs instances: starting them, taking transitions, moving through automatic
 * steps, completing and cancelling. Each call is one database transaction, so a
 * failure (a validation error, an action set to throw) leaves the instance as it was.
 */
class Engine
{
    public function __construct(
        protected WorkflowsManager $workflows,
        protected ConditionEvaluator $conditions,
        protected AssigneeResolver $assignees,
        protected ActionRunner $actions,
        protected StepForms $forms,
        protected History $history,
    ) {}

    /**
     * Start an instance of the workflow's published version. With a start form,
     * $data is validated and stored with it; without one, it's the instance's data.
     *
     * @param  Request|array<string, mixed>  $data
     */
    public function start(Workflow|string $workflow, ?Model $subject = null, ?Model $by = null, Request|array $data = [], ?string $comment = null): WorkflowInstance
    {
        $workflow = $workflow instanceof Workflow ? $workflow : $this->workflows->workflow($workflow);

        if (! $workflow->isPublished()) {
            throw WorkflowException::notPublished($workflow->key);
        }

        if (! $workflow->is_active) {
            throw WorkflowException::inactive($workflow->key);
        }

        if ($workflow->subject_type && $subject && $subject->getMorphClass() !== $workflow->subject_type && $subject::class !== $workflow->subjectClass()) {
            throw WorkflowException::wrongSubject($workflow->key, $workflow->subject_type, $subject->getMorphClass());
        }

        $version = $workflow->publishedVersion;
        $definition = $version->definition($workflow->key);
        $start = $definition->getStartStep() ?? throw WorkflowException::invalidDefinition([__('A workflow has exactly one start step.')]);

        return DB::transaction(function () use ($workflow, $version, $start, $subject, $by, $data, $comment) {
            /** @var WorkflowInstance $instance */
            $instance = new ($this->workflows->model('instance'));
            $instance->workflow()->associate($workflow);
            $instance->version()->associate($version);
            $instance->status = WorkflowInstance::ACTIVE;
            $instance->started_at = now();
            $instance->data = [];

            if ($subject) {
                $instance->subject()->associate($subject);
            }

            if ($by) {
                $instance->starter()->associate($by);
            }

            $instance->save();

            [$collected, $entry] = $this->collect($start, $data, $by, $instance);

            $instance->data = $collected;
            $instance->save();

            $this->history->record($instance, WorkflowHistory::STARTED, [
                'step' => $start->getKey(),
                'comment' => $this->clean($comment),
                'data' => $this->historyData($collected, $entry?->getKey()),
            ], $by);

            WorkflowStarted::dispatch($instance, $by);

            $this->enter($instance, $start, $by);

            return $instance->refresh();
        });
    }

    /**
     * Take a transition from the instance's current task. The user must be able to
     * act on the task (an assignee, or someone with manage_workflow_instances)
     * unless $force. The step's form, when it has one and the transition doesn't
     * skip it, validates $data; the transition's conditions are checked with it.
     *
     * @param  Request|array<string, mixed>  $data
     *
     * @throws AuthorizationException|ValidationException|WorkflowException
     */
    public function transition(WorkflowInstance $instance, string $transition, ?Model $by = null, Request|array $data = [], ?string $comment = null, bool $force = false): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $transition, $by, $data, $comment, $force) {
            /** @var WorkflowInstance $locked */
            $locked = $instance->newQuery()->lockForUpdate()->findOrFail($instance->getKey());
            $instance->setRawAttributes($locked->getAttributes(), true);

            if (! $instance->isActive()) {
                throw WorkflowException::notActive($instance->getKey());
            }

            $definition = $instance->refresh()->definition();
            $step = $instance->currentStep();
            $chosen = $definition->getTransition($transition);

            if (! $step?->isTask() || ! $chosen || $chosen->getFrom() !== $step->getKey()) {
                throw WorkflowException::unknownTransition($transition, $instance->current_step);
            }

            $task = $instance->currentTask();

            if (! $force && (! $task || ! $by || ! $this->canAct($task, $by))) {
                throw new AuthorizationException(__('You can\'t act on this task.'));
            }

            $comment = $this->clean($comment);

            if ($chosen->getComment() === Transition::COMMENT_REQUIRED && $comment === null) {
                throw ValidationException::withMessages(['comment' => __('Write a comment to :transition.', ['transition' => $this->workflows->text($chosen->getLabel()) ?? $chosen->getKey()])]);
            }

            if ($chosen->getComment() === Transition::COMMENT_NONE) {
                $comment = null;
            }

            [$collected, $entry] = $chosen->skipsForm()
                ? [is_array($data) && $step->getForm() === null ? $data : [], null]
                : $this->collect($step, $data, $by, $task ?? $instance);

            $merged = [...($instance->data ?? []), ...$collected];

            if (! $this->conditions->passes($chosen->getConditions(), $this->scope($instance, $merged))) {
                throw ValidationException::withMessages(['transition' => __('You can\'t :transition now.', ['transition' => $this->workflows->text($chosen->getLabel()) ?? $chosen->getKey()])]);
            }

            $instance->data = $merged;
            $instance->save();

            if ($task) {
                $task->fill([
                    'status' => WorkflowTask::COMPLETED,
                    'transition' => $chosen->getKey(),
                    'comment' => $comment,
                    'form_entry_id' => $entry?->getKey(),
                    'completed_at' => now(),
                ]);

                if ($by) {
                    $task->completedBy()->associate($by);
                }

                $task->save();
            }

            $this->move($instance, $chosen, $by, $comment, $task, $this->historyData($collected, $entry?->getKey()));

            return $instance->refresh();
        });
    }

    /**
     * Stop an active instance; its open tasks are cancelled
     */
    public function cancel(WorkflowInstance $instance, ?Model $by = null, ?string $reason = null): WorkflowInstance
    {
        if (! $instance->isActive()) {
            throw WorkflowException::notActive($instance->getKey());
        }

        return DB::transaction(function () use ($instance, $by, $reason) {
            $instance->tasks()->where('status', WorkflowTask::OPEN)->update(['status' => WorkflowTask::CANCELLED, 'updated_at' => now()]);

            $instance->status = WorkflowInstance::CANCELLED;
            $instance->completed_at = now();
            $instance->save();

            $this->history->record($instance, WorkflowHistory::CANCELLED, [
                'step' => $instance->current_step,
                'comment' => $this->clean($reason),
            ], $by);

            WorkflowCancelled::dispatch($instance, $by, $this->clean($reason));

            return $instance->refresh();
        });
    }

    /**
     * The transitions a user can take now, in the order they were defined.
     * Conditions are checked with the data collected so far (and again, with
     * the step's form, when one is taken).
     *
     * @return array<int, Transition>
     */
    public function availableTransitions(WorkflowInstance $instance, ?Model $user = null): array
    {
        if (! $instance->isActive() || ! $instance->currentStep()?->isTask()) {
            return [];
        }

        $task = $instance->currentTask();

        if ($user && (! $task || ! $this->canAct($task, $user))) {
            return [];
        }

        $scope = $this->scope($instance, $instance->data ?? []);

        return array_values(array_filter(
            $instance->definition()->transitionsFrom((string) $instance->current_step, defaults_last: false),
            fn (Transition $transition) => $this->conditions->passes($transition->getConditions(), $scope),
        ));
    }

    /**
     * Whether the user can act on an open task: an assignee, or someone who may
     * manage every instance
     */
    public function canAct(WorkflowTask $task, Model $user): bool
    {
        if (! $task->isOpen()) {
            return false;
        }

        if ($task->isAssignedTo($user)) {
            return true;
        }

        return method_exists($user, 'can') && $user->can(WorkflowPolicy::MANAGE_INSTANCES);
    }

    /**
     * Enter a step: run its actions, then wait (task), finish (end) or move on (automatic)
     */
    protected function enter(WorkflowInstance $instance, Step $step, ?Model $actor, ?string $from = null, int $depth = 0): void
    {
        $limit = (int) config('workflows.max_automatic_steps', 100);

        if ($depth > $limit) {
            throw WorkflowException::tooManySteps($limit);
        }

        $instance->current_step = $step->getKey();
        $instance->save();

        $this->history->record($instance, WorkflowHistory::ENTERED, ['step' => $step->getKey(), 'from_step' => $from]);

        StepEntered::dispatch($instance, $step->getKey(), $actor);

        // A task's actions run once it's assigned, so they can notify its assignees
        if ($step->isTask()) {
            $this->createTask($instance, $step);
            $this->actions->run($step->getActions(), $instance, step: $step->getKey(), actor: $actor);

            return;
        }

        $this->actions->run($step->getActions(), $instance, step: $step->getKey(), actor: $actor);

        if ($step->isEnd()) {
            $this->complete($instance, $step);

            return;
        }

        $next = $this->pick($instance, $instance->definition(), $step) ?? throw WorkflowException::noWayOut($step->getKey());

        $this->move($instance, $next, $actor, depth: $depth + 1, automatic: true);
    }

    /**
     * Move along a transition: record it, run its actions and enter the next step
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function move(WorkflowInstance $instance, Transition $transition, ?Model $actor, ?string $comment = null, ?WorkflowTask $task = null, ?array $data = null, int $depth = 0, bool $automatic = false): void
    {
        $from = (string) $transition->getFrom();
        $to = $instance->definition()->getStep((string) $transition->getTo()) ?? throw WorkflowException::noWayOut($from);

        $this->history->record($instance, WorkflowHistory::TRANSITIONED, [
            'step' => $to->getKey(),
            'from_step' => $from,
            'transition' => $transition->getKey(),
            'comment' => $comment,
            'data' => $data,
            'workflow_task_id' => $task?->getKey(),
        ], $automatic ? null : $actor);

        TransitionTaken::dispatch($instance, $transition->getKey(), $from, $to->getKey(), $automatic ? null : $actor, $comment);

        $this->actions->run($transition->getActions(), $instance, step: $from, transition: $transition->getKey(), actor: $actor);

        $this->enter($instance, $to, $actor, $from, $depth);
    }

    /**
     * The transition an automatic step takes: the first whose conditions hold,
     * those with conditions before the default
     */
    public function pick(WorkflowInstance|array $scope, WorkflowDefinition $definition, Step $step): ?Transition
    {
        $scope = $scope instanceof WorkflowInstance ? $this->scope($scope, $scope->data ?? []) : $scope;

        foreach ($definition->transitionsFrom($step->getKey()) as $transition) {
            if ($this->conditions->passes($transition->getConditions(), $scope)) {
                return $transition;
            }
        }

        return null;
    }

    protected function createTask(WorkflowInstance $instance, Step $step): WorkflowTask
    {
        /** @var WorkflowTask $task */
        $task = $instance->tasks()->make([
            'step' => $step->getKey(),
            'status' => WorkflowTask::OPEN,
            'due_at' => $step->getDueIn() ? now()->addHours($step->getDueIn()) : null,
        ]);

        $task->save();

        $this->assignees->assign($task, $step, $instance);

        $task->load('assignees');

        $this->history->record($instance, WorkflowHistory::ASSIGNED, [
            'step' => $step->getKey(),
            'workflow_task_id' => $task->getKey(),
            'data' => ['assignees' => $task->assignees->map(fn ($assignee) => ['kind' => $assignee->kind, 'label' => $assignee->label()])->all()],
        ]);

        TaskCreated::dispatch($task);

        return $task;
    }

    protected function complete(WorkflowInstance $instance, Step $step): void
    {
        $instance->status = WorkflowInstance::COMPLETED;
        $instance->outcome = $step->getOutcome() ?? $step->getKey();
        $instance->completed_at = now();
        $instance->save();

        $this->history->record($instance, WorkflowHistory::COMPLETED, ['step' => $step->getKey(), 'data' => ['outcome' => $instance->outcome]]);

        WorkflowCompleted::dispatch($instance, $instance->outcome);
    }

    /**
     * The step's data: its form's answers (validated and stored), or the array as given
     *
     * @param  Request|array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: FormEntry|null}
     */
    protected function collect(Step $step, Request|array $data, ?Model $by, Model $subject): array
    {
        if ($step->getForm() === null) {
            return [$data instanceof Request ? [] : $data, null];
        }

        $entry = $this->forms->submit($step, $data, $by, $subject);

        return [$entry?->answers() ?? [], $entry];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function scope(WorkflowInstance $instance, array $data): array
    {
        return ['data' => $data, 'subject' => $instance->subject, 'starter' => $instance->starter];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    protected function historyData(array $data, mixed $entry): ?array
    {
        $result = array_filter(['values' => $data, 'form_entry_id' => $entry], fn ($value) => $value !== null && $value !== []);

        return $result === [] ? null : $result;
    }

    protected function clean(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === '' ? null : $text;
    }
}
