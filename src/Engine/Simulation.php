<?php

namespace Javaabu\Workflows\Engine;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What the tester found: the path a run takes with the sample data, and where it stopped
 */
class Simulation implements Arrayable, JsonSerializable
{
    /** Reached an end step */
    public const string COMPLETED = 'completed';

    /** Stopped at a task that has no choice in the sample */
    public const string WAITING = 'waiting';

    /** An automatic step had no transition whose conditions held, or a choice wasn't possible */
    public const string STUCK = 'stuck';

    /** Went through more steps than the limit */
    public const string LOOPED = 'looped';

    /**
     * @param  array<int, array<string, mixed>>  $path  one entry per step visited
     * @param  array<string, mixed>  $data  the data at the end
     * @param  array<int, array{key: string, label: string|null}>  $options  the transitions open at a waiting task
     */
    public function __construct(
        public readonly string $status,
        public readonly array $path,
        public readonly array $data = [],
        public readonly ?string $outcome = null,
        public readonly ?string $message = null,
        public readonly array $options = [],
    ) {}

    public function completed(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function waiting(): bool
    {
        return $this->status === self::WAITING;
    }

    /**
     * The keys of the steps visited, in order
     *
     * @return array<int, string>
     */
    public function steps(): array
    {
        return array_map(fn (array $step) => $step['step'], $this->path);
    }

    /**
     * The keys of the transitions taken, in order
     *
     * @return array<int, string>
     */
    public function transitions(): array
    {
        return array_values(array_filter(array_map(fn (array $step) => $step['transition'] ?? null, $this->path)));
    }

    /**
     * Where it stopped
     */
    public function lastStep(): ?string
    {
        $last = $this->path === [] ? null : $this->path[array_key_last($this->path)];

        return $last['step'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'outcome' => $this->outcome,
            'message' => $this->message,
            'path' => $this->path,
            'options' => $this->options,
            'data' => $this->data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
