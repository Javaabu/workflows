---
title: AI assistant
sidebar_position: 10
---

With [laravel/ai](https://github.com/laravel/ai) installed, the workflows list has *Build with the assistant* and the builder *Change with the assistant*. Describe the workflow, or the change, and the assistant writes a draft for you to review and publish. It never publishes.

- It knows the format, the step types, the assignee types (and your custom ones and roles), your actions and their settings, your published forms and the active languages.
- Its answer is checked by the same validator as the builder. When it has problems, the assistant gets one more try with them (`assistant.attempts`). If that fails too, you get a validation error on `description`.
- Steps without positions are laid out left to right.

```php
use Javaabu\Workflows\Ai\WorkflowAssistant;

WorkflowAssistant::available();   // laravel/ai installed and workflows.assistant.enabled
['workflow' => $workflow, 'summary' => $summary] = app(WorkflowAssistant::class)->build('Purchases over 10,000 also go to the CEO', $workflow, $user);
```

Configure the provider and model with `workflows.assistant.provider` and `.model` (laravel/ai's defaults otherwise). It needs `edit_workflows`.

# Testing

Fake the agent with laravel/ai's fakes:

```php
use Javaabu\Workflows\Ai\WorkflowAssistantAgent;

WorkflowAssistantAgent::fake([['summary' => 'A purchase approval.', 'workflow' => json_encode($definition)]]);

$this->actingAs($admin)->post('/admin/workflows/assistant', ['description' => 'Purchases go to finance']);

WorkflowAssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'finance'));
```
