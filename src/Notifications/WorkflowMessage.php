<?php

namespace Javaabu\Workflows\Notifications;

use Javaabu\Workflows\Engine\DataResolver;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Models\WorkflowInstance;

/**
 * What a notify action says, worked out for each notifiable in their language:
 * the title and message (text or locale maps, with placeholders) and a link
 */
class WorkflowMessage
{
    /**
     * @param  string|array<string, string>|null  $title
     * @param  string|array<string, string>|null  $message
     */
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly string|array|null $title,
        public readonly string|array|null $message = null,
        public readonly ?string $url = null,
        public readonly ?string $color = null,
    ) {}

    public function title(): string
    {
        $title = Workflows::text($this->title) ?? Workflows::text($this->instance->workflow?->name) ?? __('Workflow');

        return (string) app(DataResolver::class)->interpolate($this->instance, $title);
    }

    public function message(): ?string
    {
        $message = Workflows::text($this->message);

        return $message === null ? null : (string) app(DataResolver::class)->interpolate($this->instance, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'workflow' => $this->instance->workflow?->key,
            'workflow_instance_id' => $this->instance->getKey(),
            'step' => $this->instance->current_step,
        ];
    }
}
