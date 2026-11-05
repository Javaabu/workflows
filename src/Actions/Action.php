<?php

namespace Javaabu\Workflows\Actions;

use Illuminate\Support\Facades\Validator;

/**
 * Something a workflow does by itself when a step is entered or a transition
 * is taken: send a notification, update the subject, call a webhook. Extend it,
 * implement key() and handle(), and register it in config('workflows.actions')
 * or with Workflows::registerAction().
 *
 * ```php
 * class CreateInvoice extends Action
 * {
 *     public static function key(): string { return 'create_invoice'; }
 *
 *     public function name(): string { return __('Create an invoice'); }
 *
 *     public function settingsRules(): array { return ['amount' => ['required', 'string']]; }
 *
 *     public function handle(ActionContext $context): void
 *     {
 *         Invoice::create(['amount' => $context->interpolate($context->setting('amount'))]);
 *     }
 * }
 * ```
 *
 * Implement Illuminate\Contracts\Queue\ShouldQueue to run it as a job after the
 * transition is saved.
 */
abstract class Action
{
    abstract public static function key(): string;

    abstract public function handle(ActionContext $context): void;

    /**
     * The action's name in the builder
     */
    public function name(): string
    {
        return str(static::key())->headline()->toString();
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * An Onu icon name for the builder
     */
    public function icon(): string
    {
        return 'zap';
    }

    /**
     * The builder's inputs for the settings: name => {type, label, help, options, required}.
     * Types are text, textarea, select, checkbox, json, key_value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function settingsFields(): array
    {
        return [];
    }

    /**
     * Validation rules for the settings
     *
     * @return array<string, mixed>
     */
    public function settingsRules(): array
    {
        return [];
    }

    /**
     * The settings' problems, as messages naming the action
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    public function validateSettings(array $settings): array
    {
        $rules = $this->settingsRules();

        if ($rules === []) {
            return [];
        }

        $validator = Validator::make($settings, $rules);

        if ($validator->passes()) {
            return [];
        }

        return array_map(fn (string $message) => "{$this->name()}: {$message}", $validator->errors()->all());
    }

    /**
     * What it would do, for the workflow tester (nothing is run there)
     */
    public function describe(ActionContext $context): string
    {
        return $this->name();
    }
}
