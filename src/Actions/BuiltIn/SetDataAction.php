<?php

namespace Javaabu\Workflows\Actions\BuiltIn;

use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\ActionContext;

/**
 * Sets values in the workflow's data, for later conditions and actions,
 * e.g. `{"needs_hr": true, "total": "{{ subject.amount }}"}`
 *
 * Settings: `values` (key => value, with `{{ placeholders }}`).
 */
class SetDataAction extends Action
{
    public static function key(): string
    {
        return 'set_data';
    }

    public function name(): string
    {
        return __('Set workflow data');
    }

    public function description(): ?string
    {
        return __('Remember values for later conditions and actions.');
    }

    public function icon(): string
    {
        return 'database';
    }

    public function settingsFields(): array
    {
        return [
            'values' => ['type' => 'key_value', 'label' => __('Values'), 'required' => true],
        ];
    }

    public function settingsRules(): array
    {
        return [
            'values' => ['required', 'array', 'min:1'],
        ];
    }

    public function handle(ActionContext $context): void
    {
        $values = (array) $context->interpolate((array) $context->setting('values', []));

        $context->instance->data = [...($context->instance->data ?? []), ...$values];
        $context->instance->save();
    }

    public function describe(ActionContext $context): string
    {
        $values = (array) $context->interpolate((array) $context->setting('values', []));

        return __('Set :values', ['values' => implode(', ', array_map(fn ($key, $value) => $key . ' = ' . (is_scalar($value) ? var_export($value, true) : json_encode($value)), array_keys($values), $values))]);
    }
}
