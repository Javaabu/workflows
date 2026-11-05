<?php

namespace Javaabu\Workflows\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Javaabu\Workflows\Concerns\HasWorkflows;

class LeaveRequest extends Model
{
    use HasWorkflows;

    protected $guarded = [];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
