<?php

namespace Javaabu\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Exceptions\WorkflowException;
use Javaabu\Workflows\Facades\Workflows;

/**
 * A version of a workflow: its steps and transitions, as one JSON document.
 * Published versions never change, and a version with instances can't be deleted.
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $number
 * @property array<string, mixed> $definition
 * @property string|null $hash
 * @property Carbon|null $published_at
 * @property-read Workflow $workflow
 */
class WorkflowVersion extends Model
{
    protected $fillable = [
        'number',
        'definition',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'published_at' => 'datetime',
            'number' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('workflows.tables.workflow_versions', 'workflow_versions');
    }

    protected static function booted(): void
    {
        static::saving(function (WorkflowVersion $version) {
            if ($version->isDirty('definition')) {
                $version->hash = static::hashOf($version->definition ?? []);
            }
        });

        static::updating(function (WorkflowVersion $version) {
            if ($version->getOriginal('published_at') !== null && $version->isDirty(['definition', 'number', 'workflow_id'])) {
                throw WorkflowException::versionPublished($version->number);
            }
        });

        static::deleting(function (WorkflowVersion $version) {
            if ($version->instances()->exists()) {
                throw WorkflowException::versionInUse($version->number);
            }
        });
    }

    /**
     * @return array{workflows: int, steps: array<int, mixed>, transitions: array<int, mixed>}
     */
    public static function emptyStructure(): array
    {
        return ['workflows' => WorkflowDefinition::FORMAT, 'steps' => [], 'transitions' => []];
    }

    /**
     * A hash of the structure, so syncing the same definition again makes no new version
     *
     * @param  array<string, mixed>|WorkflowDefinition  $structure
     */
    public static function hashOf(array|WorkflowDefinition $structure): string
    {
        $structure = $structure instanceof WorkflowDefinition ? $structure->structure() : WorkflowDefinition::fromArray($structure, 'workflow')->structure();

        return hash('sha256', (string) json_encode($structure));
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('workflow'), 'workflow_id')->withTrashed();
    }

    /**
     * @return HasMany<WorkflowInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(Workflows::model('instance'), 'workflow_version_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * The steps and transitions as a definition
     */
    public function definition(?string $key = null): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray($this->definition ?? [], $key ?? $this->workflow?->key ?? 'workflow');
    }

    /**
     * Replace the draft's steps and transitions, after validating them
     */
    public function replaceStructure(WorkflowDefinition $definition, bool $validate = true): static
    {
        if ($validate) {
            Workflows::validator()->validate($definition);
        }

        $this->definition = $definition->structure();
        $this->save();

        return $this;
    }
}
