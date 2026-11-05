<?php

namespace Javaabu\Workflows\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Javaabu\Workflows\Definitions\WorkflowDefinition;
use Javaabu\Workflows\Exceptions\WorkflowException;
use Javaabu\Workflows\Facades\Workflows;

/**
 * A workflow: its key, name and subject, and its versions. Instances start on
 * the published version and stay on it; the builder edits the draft.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $subject_type
 * @property array<string, mixed>|null $settings
 * @property bool $is_locked
 * @property bool $is_active
 * @property int|null $published_version_id
 * @property-read WorkflowVersion|null $publishedVersion
 * @property-read WorkflowVersion|null $draftVersion
 */
class Workflow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'description',
        'subject_type',
        'settings',
        'is_locked',
        'is_active',
    ];

    protected $attributes = [
        'is_locked' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return config('workflows.tables.workflows', 'workflows');
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return HasMany<WorkflowVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Workflows::model('version'), 'workflow_id')->orderBy('number');
    }

    /**
     * @return BelongsTo<WorkflowVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(Workflows::model('version'), 'published_version_id');
    }

    /**
     * @return HasOne<WorkflowVersion, $this>
     */
    public function draftVersion(): HasOne
    {
        return $this->hasOne(Workflows::model('version'), 'workflow_id')
            ->whereNull('published_at')
            ->latest('number');
    }

    /**
     * @return HasMany<WorkflowInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(Workflows::model('instance'), 'workflow_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_version_id');
    }

    /**
     * Workflows for a kind of subject: a model, its class or its morph alias
     *
     * @param  Builder<static>  $query
     */
    public function scopeFor(Builder $query, Model|string $subject): void
    {
        $type = $subject instanceof Model ? $subject->getMorphClass() : (class_exists($subject) ? Relation::getMorphAlias($subject) : $subject);

        $query->where('subject_type', $type);
    }

    public function isPublished(): bool
    {
        return $this->published_version_id !== null;
    }

    /**
     * Whether instances can be started now: published and active
     */
    public function canStart(): bool
    {
        return $this->isPublished() && $this->is_active;
    }

    public function hasDraft(): bool
    {
        return $this->draftVersion !== null;
    }

    /**
     * The model class of the subject, when the workflow has one
     *
     * @return class-string<Model>|null
     */
    public function subjectClass(): ?string
    {
        if (! $this->subject_type) {
            return null;
        }

        return Relation::getMorphedModel($this->subject_type) ?? (class_exists($this->subject_type) ? $this->subject_type : null);
    }

    /**
     * The draft, the published version, or the latest version
     */
    public function currentVersion(): ?WorkflowVersion
    {
        return $this->draftVersion
            ?? $this->publishedVersion
            ?? $this->versions()->reorder()->latest('number')->first();
    }

    /**
     * The draft the builder edits, made from the published version when there's none
     */
    public function draft(?Model $author = null): WorkflowVersion
    {
        if ($this->is_locked) {
            throw WorkflowException::locked($this->key);
        }

        return $this->ensureDraft($author);
    }

    /**
     * @internal the draft, without checking whether the workflow is locked
     */
    public function ensureDraft(?Model $author = null): WorkflowVersion
    {
        $draft = $this->draftVersion()->first();

        if ($draft) {
            return $draft;
        }

        $source = $this->publishedVersion ?? $this->versions()->reorder()->latest('number')->first();

        /** @var WorkflowVersion $draft */
        $draft = $this->versions()->make([
            'number' => $this->nextVersionNumber(),
            'definition' => $source?->definition ?? WorkflowVersion::emptyStructure(),
        ]);

        if ($author) {
            $draft->author()->associate($author);
        }

        $draft->save();

        $this->unsetRelation('draftVersion');

        return $draft;
    }

    /**
     * Publish the draft: new instances start on it, and it can't change any more
     */
    public function publish(?Model $author = null): WorkflowVersion
    {
        $draft = $this->draftVersion()->first();

        if (! $draft) {
            return $this->publishedVersion ?? throw WorkflowException::notPublished($this->key);
        }

        Workflows::validator()->validate($draft->definition($this->key));

        return DB::transaction(function () use ($draft, $author) {
            if ($author && ! $draft->author_id) {
                $draft->author()->associate($author);
            }

            $draft->published_at = now();
            $draft->save();

            $this->published_version_id = $draft->getKey();
            $this->save();

            $this->unsetRelation('draftVersion');
            $this->setRelation('publishedVersion', $draft);

            return $draft;
        });
    }

    /**
     * Throw the draft away, keeping the published version
     */
    public function discardDraft(): void
    {
        $this->draftVersion()->first()?->delete();

        $this->unsetRelation('draftVersion');
    }

    public function nextVersionNumber(): int
    {
        return ((int) $this->versions()->reorder()->max('number')) + 1;
    }

    /**
     * The definition of the published version (or the draft, or the current one)
     */
    public function definition(bool $draft = false): ?WorkflowDefinition
    {
        $version = $draft ? $this->currentVersion() : ($this->publishedVersion ?? $this->currentVersion());

        if (! $version) {
            return null;
        }

        return $version->definition($this->key)
            ->name($this->name)
            ->description($this->description)
            ->for($this->subject_type)
            ->locked($this->is_locked);
    }
}
