<?php

namespace Javaabu\Workflows\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Javaabu\Workflows\Definitions\Step;
use Javaabu\Workflows\Definitions\Transition;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Facades\Workflows;

/**
 * A run of a workflow, on the version it started with: where it is, what it
 * collected, its tasks and its history.
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $workflow_version_id
 * @property string|null $current_step
 * @property string $status
 * @property string|null $outcome
 * @property array<string, mixed>|null $data
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read Workflow $workflow
 * @property-read WorkflowVersion $version
 * @property-read Model|null $subject
 * @property-read Model|null $starter
 */
class WorkflowInstance extends Model
{
    public const string ACTIVE = 'active';

    public const string COMPLETED = 'completed';

    public const string CANCELLED = 'cancelled';

    public const array STATUSES = [self::ACTIVE, self::COMPLETED, self::CANCELLED];

    protected $fillable = [
        'current_step',
        'status',
        'outcome',
        'data',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    protected ?WorkflowDefinition $parsed_definition = null;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('workflows.tables.workflow_instances', 'workflow_instances');
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('workflow'), 'workflow_id')->withTrashed();
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('version'), 'workflow_version_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function starter(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WorkflowTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Workflows::model('task'), 'workflow_instance_id')->orderBy('id');
    }

    /**
     * @return HasMany<WorkflowHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(Workflows::model('history'), 'workflow_instance_id')->orderBy('id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::ACTIVE);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', self::COMPLETED);
    }

    /**
     * Instances about a model
     *
     * @param  Builder<static>  $query
     */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
    }

    /**
     * Instances of a workflow, by key
     *
     * @param  Builder<static>  $query
     */
    public function scopeOfWorkflow(Builder $query, string $key): void
    {
        $query->whereHas('workflow', fn (Builder $workflow) => $workflow->where('key', $key));
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    /**
     * The definition of the version the instance runs on
     */
    public function definition(): WorkflowDefinition
    {
        return $this->parsed_definition ??= $this->version->definition($this->workflow?->key);
    }

    public function currentStep(): ?Step
    {
        return $this->current_step ? $this->definition()->getStep($this->current_step) : null;
    }

    /**
     * The open tasks: the current step's, while the instance is active
     *
     * @return Collection<int, WorkflowTask>
     */
    public function openTasks()
    {
        return $this->tasks()->where('status', WorkflowTask::OPEN)->get();
    }

    public function currentTask(): ?WorkflowTask
    {
        return $this->tasks()->where('status', WorkflowTask::OPEN)->latest('id')->first();
    }

    /**
     * A value the instance collected: `answer('days')`, `answer('items.0.name')`
     */
    public function answer(string $key, mixed $default = null): mixed
    {
        return data_get($this->data ?? [], $key, $default);
    }

    /**
     * The transitions a user can take now: from the current task, whose
     * conditions hold, when they may act on it
     *
     * @return array<int, Transition>
     */
    public function availableTransitions(?Model $user = null): array
    {
        return Workflows::engine()->availableTransitions($this, $user);
    }

    /**
     * Whether the user can act on the current task
     */
    public function canAct(?Model $user): bool
    {
        $task = $this->currentTask();

        return $task !== null && $user !== null && Workflows::engine()->canAct($task, $user);
    }

    /**
     * Take a transition: see Engine::transition()
     *
     * @param  array<string, mixed>  $data
     */
    public function transition(string $transition, ?Model $by = null, array|Request $data = [], ?string $comment = null, bool $force = false): static
    {
        Workflows::engine()->transition($this, $transition, by: $by, data: $data, comment: $comment, force: $force);

        return $this->refresh();
    }

    public function cancel(?Model $by = null, ?string $reason = null): static
    {
        Workflows::engine()->cancel($this, by: $by, reason: $reason);

        return $this->refresh();
    }

    /**
     * @internal forget the parsed definition
     */
    public function refresh(): static
    {
        $this->parsed_definition = null;

        return parent::refresh();
    }
}
