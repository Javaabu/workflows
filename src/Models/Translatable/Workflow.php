<?php

namespace Javaabu\Workflows\Models\Translatable;

use Javaabu\Translatable\Contracts\JsonTranslatable;
use Javaabu\Translatable\JsonTranslatable\IsJsonTranslatable;
use Javaabu\Workflows\Models\Workflow as BaseWorkflow;

/**
 * The workflow model with javaabu/translatable: its name and description in
 * every language. Used when javaabu/translatable is on and
 * config('workflows.models.workflow') is null. PHP only loads it when it's used,
 * so it's safe without the package.
 */
class Workflow extends BaseWorkflow implements JsonTranslatable
{
    use IsJsonTranslatable;

    /** @var array<int, string> */
    protected array $translatable = ['name', 'description'];

    /**
     * Keep morph types pointing at the base model
     */
    public function getMorphClass(): string
    {
        return BaseWorkflow::class;
    }
}
