<?php

namespace Javaabu\Workflows\Actions\BuiltIn;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\ActionContext;

/**
 * Calls a URL with what happened. It's queued (after the transition is saved),
 * so a slow endpoint doesn't hold anyone up.
 *
 * Settings: `url`, `method` (POST by default), `headers`, `payload` (with
 * `{{ placeholders }}`; the default describes the instance) and `secret` (signs
 * the body: `X-Workflows-Signature: sha256=<hmac>`).
 */
class WebhookAction extends Action implements ShouldQueue
{
    public static function key(): string
    {
        return 'webhook';
    }

    public function name(): string
    {
        return __('Call a webhook');
    }

    public function description(): ?string
    {
        return __('Send what happened to another system.');
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function settingsFields(): array
    {
        return [
            'url' => ['type' => 'text', 'label' => __('URL'), 'required' => true],
            'method' => ['type' => 'select', 'label' => __('Method'), 'options' => ['POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'GET' => 'GET']],
            'headers' => ['type' => 'key_value', 'label' => __('Headers')],
            'payload' => ['type' => 'key_value', 'label' => __('Payload'), 'help' => __('Leave it empty to send the instance, its step and its data.')],
            'secret' => ['type' => 'text', 'label' => __('Signing secret')],
        ];
    }

    public function settingsRules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2000', 'regex:/^(https?:\/\/|\{\{)/'],
            'method' => ['nullable', 'in:POST,PUT,PATCH,GET'],
            'headers' => ['nullable', 'array'],
            'payload' => ['nullable', 'array'],
            'secret' => ['nullable', 'string'],
        ];
    }

    public function handle(ActionContext $context): void
    {
        $url = (string) $context->interpolate((string) $context->setting('url'));
        $method = strtoupper((string) ($context->setting('method') ?: 'POST'));
        $payload = $this->payload($context);
        $body = (string) json_encode($payload);

        $headers = array_map('strval', (array) $context->interpolate((array) $context->setting('headers', [])));

        if ($secret = $context->setting('secret')) {
            $headers['X-Workflows-Signature'] = 'sha256=' . hash_hmac('sha256', $body, (string) $secret);
        }

        $request = Http::withHeaders($headers)->acceptJson()->timeout((int) config('workflows.webhooks.timeout', 10));

        $response = $method === 'GET'
            ? $request->get($url, $payload)
            : $request->withBody($body, 'application/json')->send($method, $url);

        $response->throw();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ActionContext $context): array
    {
        $payload = $context->setting('payload');

        if (is_array($payload) && $payload !== []) {
            return (array) $context->interpolate($payload);
        }

        $instance = $context->instance;

        return [
            'workflow' => $instance->workflow?->key,
            'instance' => $instance->getKey(),
            'status' => $instance->status,
            'step' => $context->step,
            'transition' => $context->transition,
            'subject' => $instance->subject_type ? ['type' => $instance->subject_type, 'id' => $instance->subject_id] : null,
            'data' => $instance->data ?? [],
        ];
    }

    public function describe(ActionContext $context): string
    {
        return __('Call :method :url', [
            'method' => strtoupper((string) ($context->setting('method') ?: 'POST')),
            'url' => (string) $context->interpolate((string) $context->setting('url')),
        ]);
    }
}
