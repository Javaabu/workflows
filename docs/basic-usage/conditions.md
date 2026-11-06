---
title: Conditions
sidebar_position: 4
---

Conditions on a transition decide whether it can be taken. From a decision or action step they choose the way; from a task they decide which buttons people see.

```php
Transition::make('long')->from('check')->to('hr_review')
    ->when('days', '>', 5)                    // days > 5
    ->when('leave_type', ['annual', 'sick'])  // leave_type in [annual, sick]
    ->when('reason')                          // reason is filled
    ->when('notes', 'blank')
    ->when('subject.amount', '>=', 1000)
    ->whenAny();                              // any rule, instead of all of them
```

# Fields

| Field | Reads |
| --- | --- |
| `days`, `data.days` | the instance's data: what forms collected, or what `set_data` and your code set |
| `items.0.name` | nested data, with dots |
| `subject.amount`, `subject.manager.name` | the subject's attributes and relations |
| `starter.department` | whoever started the instance |

# Operators

| Operator | Holds when |
| --- | --- |
| `=`, `!=` | equal (numbers as numbers, booleans as booleans, else as text); a list equals a value it contains |
| `>`, `>=`, `<`, `<=` | numbers, then `Y-m-d` dates, then text; never when the field is empty |
| `in`, `not_in` | the value (or any of a list's values) is one of the given values |
| `contains` | a list contains the value, or text contains it (ignoring case) |
| `filled`, `blank` | Laravel's `filled()` / `blank()` |

# Decisions

A decision (or action, or start) step tries its transitions with conditions in order, and takes the first that holds; if none does, it takes the one without conditions. If there's no such transition the engine throws `WorkflowException` and nothing is saved, so give every decision a default.

# Conditional buttons

`$instance->availableTransitions($user)` (and the instance page) only offers transitions whose conditions hold with the data collected so far. Conditions are checked again, with the task's form answers, when one is taken.

# In arrays and JSON

```json
{"conditions": {"match": "all", "rules": [{"field": "days", "operator": ">", "value": 5}, {"field": "reason", "operator": "filled"}]}}
```

Check one yourself with `app(Javaabu\Workflows\Engine\ConditionEvaluator::class)->passes($conditions, $instance)`.
