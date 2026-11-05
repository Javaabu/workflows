<?php

namespace Javaabu\Workflows\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\Workflow;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * For models workflows are about (a leave request, an application):
 *
 * ```php
 * $leave->startWorkflow('leave-request', by: $user, data: $request->all());
 * $leave->workflowInstance('leave-request')?->current_step;
 * $leave->workflowInstances()->active()->get();
 * ```
 *
 * @mixin Model
 */
trait HasWorkflows
{
    /**
     * @return MorphMany<WorkflowInstance, $this>
     */
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(Workflows::model('instance'), 'subject');
    }

    /**
     * The latest instance of a workflow (any workflow when $key is null)
     */
    public function workflowInstance(?string $key = null): ?WorkflowInstance
    {
        return $this->workflowInstances()
            ->when($key, fn ($query) => $query->ofWorkflow($key))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>|Request  $data
     */
    public function startWorkflow(Workflow|string $workflow, ?Model $by = null, mixed $data = [], ?string $comment = null): WorkflowInstance
    {
        return Workflows::start($workflow, subject: $this, by: $by, data: $data, comment: $comment);
    }

    public function hasActiveWorkflow(?string $key = null): bool
    {
        return $this->workflowInstances()
            ->active()
            ->when($key, fn ($query) => $query->ofWorkflow($key))
            ->exists();
    }
}
