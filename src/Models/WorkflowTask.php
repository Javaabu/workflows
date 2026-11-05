<?php

namespace Javaabu\Workflows\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Javaabu\FormBuilder\Facades\FormBuilder;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Facades\Workflows;

/**
 * A visit to a task step: who it's assigned to, and how it was completed
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property string $step
 * @property string $status
 * @property string|null $transition
 * @property string|null $comment
 * @property int|null $form_entry_id
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property-read WorkflowInstance $instance
 * @property-read Model|null $completedBy
 */
class WorkflowTask extends Model
{
    public const string OPEN = 'open';

    public const string COMPLETED = 'completed';

    public const string CANCELLED = 'cancelled';

    protected $fillable = [
        'step',
        'status',
        'transition',
        'comment',
        'form_entry_id',
        'due_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => self::OPEN,
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('workflows.tables.workflow_tasks', 'workflow_tasks');
    }

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('instance'), 'workflow_instance_id');
    }

    /**
     * @return HasMany<WorkflowTaskAssignee, $this>
     */
    public function assignees(): HasMany
    {
        return $this->hasMany(Workflows::model('assignee'), 'workflow_task_id');
    }

    public function completedBy(): MorphTo
    {
        return $this->morphTo('completed_by');
    }

    /**
     * The javaabu/form-builder entry with the data collected at this task
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(FormBuilder::model('entry'), 'form_entry_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    /**
     * Tasks a user is assigned to: by name, by one of their roles, or by one of their permissions
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignedTo(Builder $query, Model $user): void
    {
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        $permissions = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->all() : [];

        $query->whereHas('assignees', function (Builder $assignees) use ($user, $roles, $permissions) {
            $assignees->where(function (Builder $query) use ($user) {
                $query->where('kind', WorkflowTaskAssignee::USER)
                    ->where('assignee_type', $user->getMorphClass())
                    ->where('assignee_id', $user->getKey());
            });

            if ($roles !== []) {
                $assignees->orWhere(fn (Builder $query) => $query->where('kind', WorkflowTaskAssignee::ROLE)->whereIn('value', $roles));
            }

            if ($permissions !== []) {
                $assignees->orWhere(fn (Builder $query) => $query->where('kind', WorkflowTaskAssignee::PERMISSION)->whereIn('value', $permissions));
            }
        });
    }

    /**
     * Tasks past their due time
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', self::OPEN)->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function definition(): ?Step
    {
        return $this->instance->definition()->getStep($this->step);
    }

    /**
     * Whether the user is one of the task's assignees
     */
    public function isAssignedTo(Model $user): bool
    {
        foreach ($this->assignees as $assignee) {
            if ($assignee->matches($user)) {
                return true;
            }
        }

        return false;
    }
}
