<?php

namespace Javaabu\Workflows\Actions\BuiltIn;

use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\ActionContext;

/**
 * Sets attributes on the subject, e.g. `{"status": "approved", "approved_days": "{{ days }}"}`.
 * Only fillable attributes change (the model's $fillable or $guarded).
 *
 * Settings: `attributes` (attribute => value, with `{{ placeholders }}`).
 */
class UpdateModelAction extends Action
{
    public static function key(): string
    {
        return 'update_model';
    }

    public function name(): string
    {
        return __('Update the subject');
    }

    public function description(): ?string
    {
        return __('Set attributes of the record the workflow is about.');
    }

    public function icon(): string
    {
        return 'pencil';
    }

    public function settingsFields(): array
    {
        return [
            'attributes' => ['type' => 'key_value', 'label' => __('Attributes'), 'help' => __('Use {{ days }} or {{ subject.title }} for values.'), 'required' => true],
        ];
    }

    public function settingsRules(): array
    {
        return [
            'attributes' => ['required', 'array', 'min:1'],
        ];
    }

    public function handle(ActionContext $context): void
    {
        $subject = $context->subject();

        if (! $subject) {
            return;
        }

        $subject->fill((array) $context->interpolate((array) $context->setting('attributes', [])));
        $subject->save();
    }

    public function describe(ActionContext $context): string
    {
        $attributes = (array) $context->interpolate((array) $context->setting('attributes', []));

        return __('Set :attributes on the subject', [
            'attributes' => implode(', ', array_map(fn ($key, $value) => $key . ' = ' . (is_scalar($value) ? $value : json_encode($value)), array_keys($attributes), $attributes)),
        ]);
    }
}
