<?php

namespace Javaabu\Workflows\Definitions;

/**
 * An action a step runs when it's entered, or a transition runs when it's
 * taken: the action's key and its settings, e.g. `ActionCall::make('notify', ['to' => 'starter'])`
 */
class ActionCall
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        protected string $action,
        protected array $settings = [],
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function make(string $action, array $settings = []): static
    {
        return new static($action, $settings);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * @return array{action: string, settings: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['action' => $this->action, 'settings' => $this->settings];
    }

    /**
     * @param  array{action: string, settings?: array<string, mixed>}  $array
     */
    public static function fromArray(array $array): static
    {
        return new static((string) ($array['action'] ?? ''), (array) ($array['settings'] ?? []));
    }
}
