<?php

namespace Javaabu\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Javaabu\Workflows\Facades\Workflows;

/**
 * The audit trail of an instance: one row per thing that happened, with who did it
 *
 * @property int $id
 * @property int $workflow_instance_id
 * @property int|null $workflow_task_id
 * @property string $event
 * @property string|null $step
 * @property string|null $from_step
 * @property string|null $transition
 * @property string|null $action
 * @property string|null $comment
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 * @property-read Model|null $actor
 */
class WorkflowHistory extends Model
{
    public const string STARTED = 'started';

    public const string ENTERED = 'entered';

    public const string ASSIGNED = 'assigned';

    public const string TRANSITIONED = 'transitioned';

    public const string ACTION_RAN = 'action_ran';

    public const string ACTION_FAILED = 'action_failed';

    public const string COMPLETED = 'completed';

    public const string CANCELLED = 'cancelled';

    public const ?string UPDATED_AT = null;

    protected $fillable = [
        'event',
        'step',
        'from_step',
        'transition',
        'action',
        'comment',
        'data',
        'workflow_task_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('workflows.tables.workflow_history', 'workflow_history');
    }

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('instance'), 'workflow_instance_id');
    }

    /**
     * @return BelongsTo<WorkflowTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('task'), 'workflow_task_id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
