# Common mistakes with javaabu/workflows

- **Setting `current_step`, `status` or `outcome` yourself.** Always go through `$instance->transition()` / `Workflows::transition()` / `cancel()`: they check assignees, comments, forms and conditions, run actions, fire events and write the history.
- **Changing a published version.** `WorkflowVersion` refuses it. Change the draft (`$workflow->draft()`, the builder, `sync()`), then `publish()`. Running instances keep their version.
- **A decision without a default.** If no transition's conditions hold and there's none without conditions, the engine throws and the transition is undone. Give every decision one transition without conditions (the validator allows at most one).
- **Reading text by indexing a locale map.** Names and labels may be strings or `['en' => …, 'dv' => …]`: use `Workflows::text($step->getName())`.
- **Checking `$user->hasRole()` to decide who can act.** Use `$instance->canAct($user)` or the policy's `act` ability: assignees can be users, roles, permissions or custom types, and `manage_workflow_instances` can act on any task.
- **Assuming `App\Models\User` or the `web` guard.** Users are morphs. Pass `guard` to the route macros for an admin guard, and `Assignee::user($admin)` for a specific user.
- **Doing work in listeners that should be an action.** If it belongs to the workflow (notify, update the record, call a system), use an action so it shows in the builder, the tester and the history. Use events for app-wide concerns.
- **Forgetting the form when taking a transition.** A task with a form validates `data`; pass the request (or the answers). Use `->skipForm()` on transitions like Reject.
- **Calling a webhook inline.** The `webhook` action is queued; your own slow actions should implement `ShouldQueue` too.
- **Renaming a step's key on a published workflow.** Instances and history refer to keys. Add a step and move the transitions instead.
- **Physical CSS or untranslated strings in custom views.** Use logical utilities (`ms-`, `ps-`, `text-start`) and `__()` with Dhivehi in `lang/dv.json`.
- **Importing workflows.css by hand on Inertia pages, or `react()` for the Blade entry.** The pages and `@javaabu/workflows` import the styles, so Vite bundles them. The Blade entry is a plain script loaded prebuilt; `manifest:check --scope=pages` lists React and `@vitejs/plugin-react` for apps that build it themselves.
