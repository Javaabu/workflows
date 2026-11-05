<?php

namespace Javaabu\Workflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Workflows\Definitions\ActionCall;
use Javaabu\Workflows\Engine\DataResolver;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * What an action runs with: the instance, its settings, the step or transition
 * that ran it and who caused it
 */
class ActionContext
{
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly ActionCall $call,
        public readonly ?string $step = null,
        public readonly ?string $transition = null,
        public readonly ?Model $actor = null,
        public readonly bool $simulated = false,
    ) {}

    /**
     * A setting, e.g. setting('to'), setting('headers.Accept')
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->call->setting($key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->call->getSettings();
    }

    public function subject(): ?Model
    {
        return $this->instance->subject;
    }

    /**
     * The workflow's data: data('days'), or all of it
     */
    public function data(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? ($this->instance->data ?? []) : $this->instance->answer($key, $default);
    }

    /**
     * Read a value the way conditions do: `days`, `data.days`, `subject.amount`, `starter.email`
     */
    public function value(string $path): mixed
    {
        return app(DataResolver::class)->get($this->instance, $path);
    }

    /**
     * Fill `{{ days }}`, `{{ subject.title }}` and `{{ starter.name }}` placeholders in text.
     * A value given alone (`{{ days }}`) keeps its type.
     */
    public function interpolate(mixed $value): mixed
    {
        return app(DataResolver::class)->interpolate($this->instance, $value);
    }
}
