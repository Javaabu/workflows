# Changelog

All notable changes to `javaabu/workflows` are documented in this file.

## Unreleased

Initial development of v1.0:

- Workflow definitions: steps (start, task, decision, action, end), transitions with labels, colours, comments and conditions, assignees and actions, as one JSON document per version. Drafts and immutable published versions; instances keep the version they started on.
- Fluent definitions (`Workflows::define()`, `Step`, `Transition`, `Assignee`) and an idempotent, lockable `sync()` for seeders. `DefinitionValidator` checks every definition.
- The engine: `Workflows::start()`, `transition()`, `cancel()`; automatic steps, a loop guard, one database transaction per call. Tasks with user, role and permission assignees, due times, `WorkflowTask::assignedTo()`. A history row for everything that happens, and events after commit.
- Assignee types: starter, user, role, permission, subject, earlier step, and custom resolvers (`Workflows::assignee()`).
- Conditions on transitions over the data, the subject and the starter, with `all` / `any`.
- Actions: `notify` (javaabu/notifications when installed, a plain Laravel notification otherwise), `update_model`, `set_data`, `webhook` (queued, signed), and custom `Action` classes. Failures are recorded and reported, or undo the transition.
- Step data with javaabu/form-builder forms on the start step and tasks.
- The tester: `Workflows::simulate()` and a tester page.
- `Route::workflows()` and `Route::workflowTasks()` with overridable controllers, `WorkflowPolicy`, `WorkflowInstancePolicy` and seven permissions through javaabu/permissions, for any guard.
- Blade (javaabu/forms) and Inertia React (@javaabu/forms, Onu) pages: workflows, settings, the builder, versions, the tester, instances, my tasks, starting.
- The visual builder (`WorkflowBuilder`): an accessible node graph with keyboard moves and connections, an inspector, a list view and announcements; mounted on the Blade page by the prebuilt `workflows::builder` entry. npm dependencies only the pages need are declared `for: 'pages'`.
- Optional javaabu/translatable support and an optional laravel/ai assistant.
- English and Dhivehi, right to left.
- Docs, `llms.txt`, the Laravel Boost guideline and skill.
- The Blade entry is a plain script (`builder.ts`) and React, `react-dom` and `@vitejs/plugin-react` are declared for manifest's `pages` scope, so Blade apps using the prebuilt entry aren't asked for them. Inertia pages and `@javaabu/workflows` import the package styles, so Vite bundles them; every Blade page loads `workflows::styles`.
