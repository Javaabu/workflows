<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
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
            Assignee::STARTER => $this->rowsFor($instance->starter),
            Assignee::USER => $this->user((string) $value),
            Assignee::ROLE => [['kind' => WorkflowTaskAssignee::ROLE, 'value' => $value]],
            Assignee::PERMISSION => [['kind' => WorkflowTaskAssignee::PERMISSION, 'value' => $value]],
            Assignee::SUBJECT => $this->rowsFor($instance->subject ? data_get($instance->subject, (string) $value) : null),
            Assignee::STEP => $this->rowsFor($this->lastActor($instance, (string) $value)),
            default => $this->custom($assignee, $instance),
        };
    }

    /**
     * The people an assignee stands for, now: users, everyone with a role,
     * everyone with a permission (directly or through a role)
     *
     * @return Collection<int, Model>
     */
    public function users(Assignee $assignee, WorkflowInstance $instance): Collection
    {
        $users = collect();

        foreach ($this->resolve($assignee, $instance) as $row) {
            $users = $users->merge($this->usersFor($row));
        }

        return $users->unique(fn (Model $user) => $user->getMorphClass() . ':' . $user->getKey())->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return iterable<int, Model>
     */
    protected function usersFor(array $row): iterable
    {
        if ($row['kind'] === WorkflowTaskAssignee::USER) {
            $class = Relation::getMorphedModel($row['type']) ?? $row['type'];
            $user = class_exists($class) ? $class::query()->find($row['id']) : null;

            return $user ? [$user] : [];
        }

        $models = config('permission.models', []);
        $class = $row['kind'] === WorkflowTaskAssignee::ROLE ? ($models['role'] ?? null) : ($models['permission'] ?? null);

        if (! $class || ! class_exists($class)) {
            return [];
        }

        $users = collect();

        foreach ($class::query()->where('name', $row['value'])->get() as $record) {
            $users = $users->merge($record->users);

            if ($row['kind'] === WorkflowTaskAssignee::PERMISSION) {
                foreach ($record->roles as $role) {
                    $users = $users->merge($role->users);
                }
            }
        }

        return $users;
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
    protected function rowsFor(mixed $users): array
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
            $rows = [...$rows, ...($item instanceof Assignee ? $this->resolve($item, $instance) : $this->rowsFor($item))];
        }

        return $rows;
    }
}
