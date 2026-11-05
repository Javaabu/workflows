<?php

namespace Javaabu\Workflows\Facades;

use Illuminate\Support\Facades\Facade;
use Javaabu\Workflows\WorkflowsManager;

/**
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $name)
 * @method static \Javaabu\Workflows\Definitions\WorkflowDefinition define(string $key)
 * @method static \Javaabu\Workflows\Models\Workflow sync(\Javaabu\Workflows\Definitions\WorkflowDefinition $definition, ?bool $publish = null)
 * @method static \Javaabu\Workflows\Models\Workflow workflow(string $key)
 * @method static \Javaabu\Workflows\Models\WorkflowInstance start(\Javaabu\Workflows\Models\Workflow|string $workflow, ?\Illuminate\Database\Eloquent\Model $subject = null, ?\Illuminate\Database\Eloquent\Model $by = null, \Illuminate\Http\Request|array $data = [], ?string $comment = null)
 * @method static \Javaabu\Workflows\Models\WorkflowInstance transition(\Javaabu\Workflows\Models\WorkflowInstance $instance, string $transition, ?\Illuminate\Database\Eloquent\Model $by = null, \Illuminate\Http\Request|array $data = [], ?string $comment = null, bool $force = false)
 * @method static \Javaabu\Workflows\Models\WorkflowInstance cancel(\Javaabu\Workflows\Models\WorkflowInstance $instance, ?\Illuminate\Database\Eloquent\Model $by = null, ?string $reason = null)
 * @method static \Javaabu\Workflows\Engine\Engine engine()
 * @method static \Javaabu\Workflows\Definitions\DefinitionValidator validator()
 * @method static WorkflowsManager registerAction(\Javaabu\Workflows\Actions\Action|string|array $actions)
 * @method static WorkflowsManager forgetAction(string $key)
 * @method static bool hasAction(string $key)
 * @method static \Javaabu\Workflows\Actions\Action action(string $key)
 * @method static array<string, \Javaabu\Workflows\Actions\Action> actions()
 * @method static WorkflowsManager assignee(string $type, callable|string $resolver, ?string $label = null)
 * @method static ?string text(string|array|null $text, ?string $locale = null)
 *
 * @see WorkflowsManager
 */
class Workflows extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowsManager::class;
    }
}
