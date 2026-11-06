---
title: The builder
sidebar_position: 7
---

The builder page (`Route::workflows()`, then a workflow) draws the draft as a diagram and edits it. It's the React `WorkflowBuilder` in both stacks: the Inertia page renders it, and the Blade page mounts it with the prebuilt `workflows::builder` script.

# Using it

- **Add a step**: choose its type in the toolbar and press *Add a step*. With a step selected, the new one goes after it, connected to it.
- **Move a step**: drag it, or Tab to it and use the arrow keys (with Shift to move further).
- **Connect steps**: drag from a step's handle (the dot on its inline-end side) to another step, or select a step and use *Go to* and *Add a transition* in the inspector.
- **Edit**: click a step or a transition's label, or press Enter on it. The inspector edits names (in every language with translatable), keys, types, assignees, forms, due times, colours, outcomes, conditions, comments and actions with their settings.
- **Delete**: the inspector's *Delete* button, or Delete / Backspace on a focused step or label. Deleting a step deletes its transitions.
- **List view**: the steps and their transitions as a list, an alternative to the diagram.
- **Save**: *Save draft* or Ctrl/⌘+S. The draft is saved as it is, even half built, and the problems that block publishing are listed; each links to its step or transition, which is outlined on the diagram.
- **Publish** (with `publish_workflows`) makes the draft the version new instances start on. *Discard draft* goes back to the published version.

Moves, selections, additions and deletions are announced to screen readers. Positions are logical (from the inline start), so the diagram mirrors in right-to-left pages.

Locked workflows (defined in code) and people without `edit_workflows` see the builder read-only.

# Saving from your own code

The builder PUTs the definition to `{prefix}/{workflow}/definition` as JSON and gets back `errors`, `unreachable` and the saved `definition`:

```bash
curl -X PUT /admin/workflows/leave-request/definition -H 'Accept: application/json' -d '{"definition": {"workflows": 1, "steps": [...], "transitions": [...]}}'
```

See [React](../advanced-usage/react.md) for using `WorkflowBuilder` on a page of your own.
