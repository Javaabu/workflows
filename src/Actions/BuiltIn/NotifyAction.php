<?php

namespace Javaabu\Workflows\Actions\BuiltIn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\ActionContext;
use Javaabu\Workflows\Definitions\Assignee;
use Javaabu\Workflows\Engine\AssigneeResolver;
use Javaabu\Workflows\Facades\Workflows;
use Javaabu\Workflows\Notifications\WorkflowMessage;
use Javaabu\Workflows\Notifications\WorkflowNotifications;

/**
 * Sends a notification: to the open task's assignees, the starter, a role,
 * a permission, a user or someone related to the subject. With
 * javaabu/notifications it's a BaseNotification (every channel, the bell).
 *
 * Settings: `to` (assignees: `[{"type": "assignees"}, {"type": "role", "value": "hr"}]`),
 * `title`, `message` (text or locale maps, with `{{ placeholders }}`), `channels`.
 */
class NotifyAction extends Action
{
    /** The `to` type for the current task's assignees */
    public const string ASSIGNEES = 'assignees';

    public static function key(): string
    {
        return 'notify';
    }

    public function name(): string
    {
        return __('Send a notification');
    }

    public function description(): ?string
    {
        return __('Tell the assignees, the starter or anyone else what happened.');
    }

    public function icon(): string
    {
        return 'bell';
    }

    public function settingsFields(): array
    {
        return [
            'to' => ['type' => 'assignees', 'label' => __('Send to'), 'required' => true, 'extra' => [self::ASSIGNEES => __('The task\'s assignees')]],
            'title' => ['type' => 'text', 'label' => __('Title'), 'help' => __('Use {{ days }} or {{ subject.title }} for values.'), 'localized' => true],
            'message' => ['type' => 'textarea', 'label' => __('Message'), 'localized' => true],
            'channels' => ['type' => 'checkboxes', 'label' => __('Channels'), 'options' => ['database' => __('In the app'), 'mail' => __('Email'), 'sms' => __('SMS')]],
        ];
    }

    public function settingsRules(): array
    {
        return [
            'to' => ['required', 'array', 'min:1'],
            'to.*.type' => ['required', 'string'],
            'title' => ['nullable'],
            'message' => ['nullable'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string'],
        ];
    }

    public function handle(ActionContext $context): void
    {
        $recipients = $this->recipients($context);

        if ($recipients->isEmpty()) {
            return;
        }

        $message = new WorkflowMessage(
            instance: $context->instance,
            title: $context->setting('title'),
            message: $context->setting('message'),
            url: Workflows::instanceUrl($context->instance),
            color: $context->setting('color'),
        );

        $channels = array_values((array) ($context->setting('channels') ?: ['database', 'mail']));

        Notification::send($recipients, WorkflowNotifications::make($message, $channels));
    }

    /**
     * @return Collection<int, Model>
     */
    public function recipients(ActionContext $context): Collection
    {
        $resolver = app(AssigneeResolver::class);
        $users = collect();

        foreach ((array) $context->setting('to', []) as $to) {
            if (! is_array($to) || ! isset($to['type'])) {
                continue;
            }

            if ($to['type'] === self::ASSIGNEES) {
                $users = $users->merge($this->assignees($context, $resolver));

                continue;
            }

            $users = $users->merge($resolver->users(Assignee::fromArray($to), $context->instance));
        }

        return $users
            ->filter(fn ($user) => $user instanceof Model && method_exists($user, 'notify'))
            ->unique(fn (Model $user) => $user->getMorphClass() . ':' . $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, Model>
     */
    protected function assignees(ActionContext $context, AssigneeResolver $resolver): Collection
    {
        $task = $context->instance->currentTask();
        $step = $task?->definition();

        if (! $step) {
            return collect();
        }

        $users = collect();

        foreach ($step->getAssignees() as $assignee) {
            $users = $users->merge($resolver->users($assignee, $context->instance));
        }

        return $users;
    }

    public function describe(ActionContext $context): string
    {
        $to = array_map(fn ($to) => is_array($to) ? trim(($to['type'] ?? '') . ' ' . ($to['value'] ?? '')) : '', (array) $context->setting('to', []));

        return __('Send ":title" to :to', [
            'title' => Workflows::text($context->setting('title')) ?? $this->name(),
            'to' => implode(', ', array_filter($to)),
        ]);
    }
}
