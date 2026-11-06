---
title: The JSON format
sidebar_position: 4
---

A definition is one JSON document. A version stores its steps and transitions; the export (and the AI assistant) add the workflow's key, name, description, subject and lock.

```json
{
    "workflows": 1,
    "key": "leave-request",
    "name": {"en": "Leave request", "dv": "ޗުއްޓީގެ އެދުން"},
    "description": null,
    "subject": "leave_request",
    "locked": false,
    "steps": [
        {"key": "start", "type": "start", "name": "Submitted", "form": "leave-request", "position": {"x": 40, "y": 120}},
        {"key": "review", "type": "task", "name": "Manager review", "assignees": [{"type": "subject", "value": "manager"}], "form": "leave-review", "form_version": 2, "due_in": 48, "position": {"x": 240, "y": 120}},
        {"key": "approved", "type": "end", "name": "Approved", "outcome": "approved", "color": "success",
         "actions": [{"action": "update_model", "settings": {"attributes": {"status": "approved"}}}]}
    ],
    "transitions": [
        {"key": "submit", "from": "start", "to": "review"},
        {"key": "approve", "from": "review", "to": "approved", "label": "Approve", "color": "success",
         "conditions": {"match": "all", "rules": [{"field": "approved_days", "operator": ">", "value": 0}]},
         "comment": "optional", "skip_form": false, "actions": []}
    ]
}
```

Empty values are left out. Text (`name`, `description`, `label`, text action settings) is a string or a map of locale codes to strings.

```php
$array = $workflow->definition()->toArray();           // the export format
$structure = $workflow->definition()->structure();     // what a version stores
$definition = WorkflowDefinition::fromArray($array);   // and back
$definition->sync();                                   // e.g. to import one
```

`GET {prefix}/{workflow}/export` downloads it.
