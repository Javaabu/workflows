<?php

namespace Javaabu\Workflows\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The laravel/ai agent that designs workflows: it gets a description (and the
 * current workflow, when changing one) and answers with the whole workflow as
 * JSON. Only loaded when laravel/ai is installed.
 *
 * Fake it in tests: WorkflowAssistantAgent::fake([['summary' => '…', 'workflow' => '{…}']]).
 */
class WorkflowAssistantAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        protected string $instructions,
    ) {}

    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->description('One or two sentences on what you built or changed, in the language of the request.')->required(),
            'workflow' => $schema->string()->description('The whole workflow as JSON: {"workflows": 1, "key": "...", "name": "...", "steps": [...], "transitions": [...]}.')->required(),
        ];
    }
}
