---
title: Custom actions
sidebar_position: 2
---

Write an action for anything your workflows do by themselves: create an invoice, book a room, post to another system.

```php
namespace App\Workflows\Actions;

use App\Models\Invoice;
use Javaabu\Workflows\Actions\Action;
use Javaabu\Workflows\Actions\ActionContext;

class CreateInvoiceAction extends Action
{
    public static function key(): string
    {
        return 'create_invoice';
    }

    public function name(): string
    {
        return __('Create an invoice');
    }

    public function description(): ?string
    {
        return __('Bill the applicant for the fee.');
    }

    public function icon(): string
    {
        return 'receipt';
    }

    /** The builder's inputs: text, textarea, select, checkboxes, key_value or assignees */
    public function settingsFields(): array
    {
        return [
            'amount' => ['type' => 'text', 'label' => __('Amount'), 'help' => __('A number, or {{ fee }}'), 'required' => true],
            'currency' => ['type' => 'select', 'label' => __('Currency'), 'options' => ['MVR' => 'MVR', 'USD' => 'USD']],
        ];
    }

    public function settingsRules(): array
    {
        return [
            'amount' => ['required'],
            'currency' => ['nullable', 'in:MVR,USD'],
        ];
    }

    public function handle(ActionContext $context): void
    {
        Invoice::create([
            'billable_type' => $context->instance->subject_type,
            'billable_id' => $context->instance->subject_id,
            'amount' => $context->interpolate($context->setting('amount')),
            'currency' => $context->setting('currency', 'MVR'),
        ]);
    }

    /** What the tester shows instead of running it */
    public function describe(ActionContext $context): string
    {
        return __('Invoice :amount', ['amount' => $context->interpolate($context->setting('amount'))]);
    }
}
```

Register it:

```php
// config/workflows.php
'actions' => [
    ...
    App\Workflows\Actions\CreateInvoiceAction::class,
],

// or in a service provider's boot()
Workflows::registerAction(CreateInvoiceAction::class);
```

`registerAction()` replaces an action with the same key, so you can swap a built-in one for a subclass. `forgetAction('webhook')` removes one from the builder and the validator.

# The context

| | |
| --- | --- |
| `$context->instance` | the `WorkflowInstance` |
| `$context->call` | the `ActionCall`: `getAction()`, `getSettings()` |
| `$context->step`, `$context->transition` | the step key (and the transition key, for a transition's actions) |
| `$context->actor` | who caused it, or null |
| `$context->simulated` | true in the tester |
| `setting($key, $default)`, `settings()` | the settings, with dots |
| `subject()`, `data($key)`, `value('subject.amount')` | what conditions read |
| `interpolate($value)` | fills `{{ placeholders }}` in a string or an array |

# Queued actions

Implement `Illuminate\Contracts\Queue\ShouldQueue` and the action runs as a job after the start or transition is saved.

# The tester

`simulate(ActionContext $context)` runs instead of `handle()` in the tester. It may only change the (unsaved) instance's data, as `set_data` does, so later decisions see it. The default does nothing.
