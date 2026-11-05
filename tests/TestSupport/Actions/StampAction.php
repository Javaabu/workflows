<?php

namespace Javaabu\Workflows\Tests\TestSupport\Actions;

use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\ActionContext;
use RuntimeException;

/**
 * Records what it ran with, or throws when told to
 */
class StampAction extends Action
{
    /** @var array<int, array<string, mixed>> */
    public static array $runs = [];

    public static function key(): string
    {
        return 'stamp';
    }

    public function settingsRules(): array
    {
        return ['label' => ['required', 'string']];
    }

    public function handle(ActionContext $context): void
    {
        if ($context->setting('fail')) {
            throw new RuntimeException('The stamp broke.');
        }

        static::$runs[] = [
            'label' => $context->interpolate($context->setting('label')),
            'step' => $context->step,
            'transition' => $context->transition,
            'actor' => $context->actor?->getKey(),
        ];
    }
}
