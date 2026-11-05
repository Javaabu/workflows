<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowInstance;
use Javaabu\Workflows\Models\WorkflowTask;
use Javaabu\Workflows\Models\WorkflowTaskAssignee;
use Javaabu\Workflows\WorkflowsManager;

/**
 * Turns a step's assignees into a task's assignee rows: users (as morphs),
 * roles and permissions
 */
class AssigneeResolver
{
    public function __construct(
        protected WorkflowsManager $workflows,
    ) {}

    public function assign(WorkflowTask $task, Step $step, WorkflowInstance $instance): void
    {
        $rows = [];

        foreach ($step->getAssignees() as $assignee) {
            foreach ($this->resolve($assignee, $instance) as $row) {
                $rows[implode('|', $row)] = $row;
            }
        }

        foreach ($rows as $row) {
            /** @var WorkflowTaskAssignee $model */
            $model = $task->assignees()->make(['kind' => $row['kind'], 'value' => $row['value'] ?? null]);

            if ($row['kind'] === WorkflowTaskAssignee::USER) {
                $model->assignee_type = $row['type'];
                $model->assignee_id = $row['id'];
            }

            $model->save();
        }
    }

    /**
     * @return array<int, array{kind: string, type?: string, id?: int|string, value?: string|null}>
     */
    public function resolve(Assignee $assignee, WorkflowInstance $instance): array
    {
        $value = $assignee->getValue();

        return match ($assignee->getType()) {
            Assignee::STARTER => $this->users($instance->starter),
            Assignee::USER => $this->user((string) $value),
            Assignee::ROLE => [['kind' => WorkflowTaskAssignee::ROLE, 'value' => $value]],
            Assignee::PERMISSION => [['kind' => WorkflowTaskAssignee::PERMISSION, 'value' => $value]],
            Assignee::SUBJECT => $this->users($instance->subject ? data_get($instance->subject, (string) $value) : null),
            Assignee::STEP => $this->users($this->lastActor($instance, (string) $value)),
            default => $this->custom($assignee, $instance),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function user(string $value): array
    {
        if (! str_contains($value, ':')) {
            return [];
        }

        [$type, $id] = explode(':', $value, 2);

        // A class name works too: store its morph alias
        if (class_exists($type)) {
            $type = Relation::getMorphAlias($type);
        }

        return [['kind' => WorkflowTaskAssignee::USER, 'type' => $type, 'id' => $id]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function users(mixed $users): array
    {
        if ($users instanceof Model) {
            return [['kind' => WorkflowTaskAssignee::USER, 'type' => $users->getMorphClass(), 'id' => $users->getKey()]];
        }

        if (! is_iterable($users)) {
            return [];
        }

        $rows = [];

        foreach ($users as $user) {
            if ($user instanceof Model) {
                $rows[] = ['kind' => WorkflowTaskAssignee::USER, 'type' => $user->getMorphClass(), 'id' => $user->getKey()];
            }
        }

        return $rows;
    }

    protected function lastActor(WorkflowInstance $instance, string $step): ?Model
    {
        $task = Workflows::model('task')::query()
            ->where('workflow_instance_id', $instance->getKey())
            ->where('step', $step)
            ->where('status', WorkflowTask::COMPLETED)
            ->latest('id')
            ->first();

        if ($task) {
            return $task->completedBy;
        }

        // The start step has no task: whoever started the instance did it
        return $instance->definition()->getStep($step)?->isStart() ? $instance->starter : null;
    }

    /**
     * An app's resolver returns users, or Assignee definitions to resolve in turn
     *
     * @return array<int, array<string, mixed>>
     */
    protected function custom(Assignee $assignee, WorkflowInstance $instance): array
    {
        $result = $this->workflows->resolveAssignee($assignee->getType(), $instance, $assignee->getValue());

        $rows = [];

        foreach (is_iterable($result) && ! $result instanceof Model ? $result : [$result] as $item) {
            $rows = [...$rows, ...($item instanceof Assignee ? $this->resolve($item, $instance) : $this->users($item))];
        }

        return $rows;
    }
}
