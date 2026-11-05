<?php

namespace Javaabu\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Javaabu\Workflows\Facades\Workflows;

/**
 * One assignee of a task: a user, a role or a permission
 *
 * @property int $id
 * @property string $kind
 * @property string|null $assignee_type
 * @property int|string|null $assignee_id
 * @property string|null $value
 * @property-read Model|null $assignee
 */
class WorkflowTaskAssignee extends Model
{
    public const string USER = 'user';

    public const string ROLE = 'role';

    public const string PERMISSION = 'permission';

    protected $fillable = [
        'kind',
        'value',
    ];

    public function getTable(): string
    {
        return config('workflows.tables.workflow_task_assignees', 'workflow_task_assignees');
    }

    /**
     * @return BelongsTo<WorkflowTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('task'), 'workflow_task_id');
    }

    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    public function matches(Model $user): bool
    {
        return match ($this->kind) {
            self::USER => $this->assignee_type === $user->getMorphClass() && (string) $this->assignee_id === (string) $user->getKey(),
            self::ROLE => $this->value !== null && method_exists($user, 'hasRole') && $user->hasRole($this->value),
            self::PERMISSION => $this->value !== null && method_exists($user, 'can') && $user->can($this->value),
            default => false,
        };
    }

    /**
     * What to show: the user's name, or the role or permission
     */
    public function label(): string
    {
        if ($this->kind === self::USER) {
            $user = $this->assignee;

            return $user ? (string) ($user->getAttribute('name') ?? $user->getKey()) : '—';
        }

        return (string) $this->value;
    }
}
