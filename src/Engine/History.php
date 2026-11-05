<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowHistory;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * Writes an instance's audit trail
 */
class History
{
    /**
     * @param  array<string, mixed>  $attributes  step, from_step, transition, action, comment, data, workflow_task_id
     */
    public function record(WorkflowInstance $instance, string $event, array $attributes = [], ?Model $actor = null): WorkflowHistory
    {
        /** @var WorkflowHistory $entry */
        $entry = new (Workflows::model('history'));

        $entry->fill(['event' => $event, ...array_filter($attributes, fn ($value) => $value !== null && $value !== [])]);
        $entry->instance()->associate($instance);

        if ($actor) {
            $entry->actor()->associate($actor);
        }

        $entry->save();

        return $entry;
    }
}
