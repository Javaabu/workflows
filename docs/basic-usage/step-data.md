---
title: Step data with form-builder
sidebar_position: 6
---

The start step and tasks can collect data with a [javaabu/form-builder](https://github.com/Javaabu/form-builder) form. Build the form in form-builder (or define it in a seeder), then name it on the step:

```php
FormBuilder::define('leave-review')->sections([
    Section::make('main')->fields([
        Field::number('approved_days')->label('Approved days')->required(),
        Field::textarea('note')->label('Note')->thaana(),
    ]),
])->sync();

Step::task('review')->form('leave-review');      // the form's published version
Step::task('review')->form('leave-review', 2);   // always version 2
```

# What happens

- **Starting**: `Workflows::start(..., data: $request)` validates the start form, stores an entry with the instance as its subject, and puts the answers in the instance's data.
- **Taking a transition** from a task with a form validates it (`ValidationException` with the form's errors), stores an entry with the task as its subject (`$task->entry`), and merges the answers into the instance's data. Later answers replace earlier ones with the same key.
- **Skipping**: a transition with `skipForm()` (Reject, Send back) takes no form data. The pages mark its button `formnovalidate`, so the browser doesn't ask for required fields either.

Conditions and actions then read the answers: `->when('approved_days', '>', 5)`, `'{{ approved_days }}'`.

# On the pages

The instance page renders the current task's form (embedded stencil fields) with a comment box and one button per available transition, all in one form, in Blade and in React. Below it, every completed step's entry is shown read-only (`FormBuilder::display()`).

# In your own code

```php
use Javaabu\Workflows\Engine\StepForms;

$forms = app(StepForms::class);
$forms->version($step);                         // the FormVersion the step fills
$forms->stencil($step, $url)?->embedded();      // its stencil form, to render yourself
FormEntry::forSubject($task)->first();          // what a task collected
```
