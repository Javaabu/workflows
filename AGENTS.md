# workflows: notes for AI coding agents

`javaabu/workflows` is a Laravel package for dynamic workflows. It stores:

- definitions, as versions with one JSON document of steps and transitions;
- instances, their tasks (with assignee rows) and their history.

The engine (`src/Engine`) runs instances; definitions are validated by `DefinitionValidator`. Admins build workflows on `Route::workflows()` pages and people act on `Route::workflowTasks()` pages (Blade or Inertia React). The package also ships TypeScript (imported by apps as `@javaabu/workflows` through javaabu/manifest) and a prebuilt `dist/` for the Blade entry, which bundles React.

These are public contracts: the definition classes and JSON format, `WorkflowsManager`/`Workflows`, the models, `Action`/`ActionContext`, the events, the route macros and route names, the page props and the `@javaabu/workflows` exports.

## Layout

| Path | What |
| --- | --- |
| `src/Definitions` | `WorkflowDefinition`, `Step`, `Transition`, `Assignee`, `Condition(s)`, `ActionCall`, `DefinitionValidator` |
| `src/Models`, `database/migrations` | `Workflow` (and `Translatable\Workflow`), `WorkflowVersion` (immutable once published), `WorkflowInstance`, `WorkflowTask`, `WorkflowTaskAssignee`, `WorkflowHistory` |
| `src/Engine` | `Engine` (start, transition, cancel), `ConditionEvaluator`, `DataResolver`, `AssigneeResolver`, `StepForms` (form-builder), `History`, `Simulator`/`Simulation` |
| `src/Actions`, `src/Jobs`, `src/Notifications` | `Action`, `ActionContext`, `ActionRunner`, the built-in actions, `RunWorkflowAction`, the notify action's notifications |
| `src/Routing`, `src/Http`, `src/Policies`, `src/Views`, `src/Builder` | Route macros, controllers, policies, `ViewRenderer`, `BuilderData` (page data), `WorkflowForms` (settings stencil form) |
| `src/Ai` | `WorkflowAssistant`, `WorkflowAssistantAgent` (optional laravel/ai) |
| `src/Support` | `Translations` (optional translatable, feature-detected), `Text`, `Strings` (page strings), `SampleModel` |
| `resources/views` | Blade pages (javaabu/forms) and partials |
| `resources/js` | TypeScript: `builder/` (WorkflowBuilder, Canvas, Inspector, definition helpers), the Inertia `Pages/`, `components/`, the Blade entry `blade/builder.ts` |
| `resources/css/workflows.css` | Styles (logical properties only) |
| `dist/` | Prebuilt Blade entry and styles, **committed** |
| `lang/en.json`, `lang/dv.json` | Translations; English strings are the keys |
| `tests/js/fixtures` | Page props written by `InertiaPagesTest`, rendered by the JS tests |
| `docs`, `llms.txt`, `resources/boost` | Docs, the index for assistants, the Laravel Boost guideline and skill |

## Rules

- Follow the Javaabu PHP guidelines (`vendor/javaabu/boost-javaabu-guidelines`): snake_case variables and properties, camelCase methods, `#[Test]` methods named `it_…`, early returns, no `else`. Pint enforces formatting.
- Instances change only through `Engine` (one DB transaction per call, the instance row locked). Never write `current_step`/`status` elsewhere. Every change records history and fires an event.
- Definitions change only through drafts (`Workflow::draft()`, `WorkflowVersion::replaceStructure()`), validated with `DefinitionValidator`. Published versions are immutable.
- The builder's behaviour lives in `resources/js/builder`; the Blade and Inertia builder pages render the same component. Keep `definition.ts` pure.
- When page props change, run `WORKFLOWS_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter InertiaPagesTest` and commit `tests/js/fixtures`.
- After changing `resources/js` code the Blade entry imports, or `resources/css`, run `pnpm build` and commit `dist/`. CI fails when it's stale.
- npm dependencies only the Inertia pages need go in `npm(..., for: 'pages')` in `Assets`; the Blade entry is a `react()` asset.
- Nothing may assume the `web` guard or `App\Models\User`. HTTP tests use two guards: `web` → `User` and `web_admin` → `Admin`.
- Inertia, javaabu/notifications, javaabu/translatable and laravel/ai are optional: feature-detect them (`Support\Translations`, `WorkflowNotifications::usesJavaabuNotifications()`, `WorkflowAssistant::available()`), keep them in `suggest`, and keep test classes loadable without them. CI has a leg without them.
- Every user-facing string goes through `__()` with an English key and gets a line in `lang/en.json` and `lang/dv.json` (`LangTest` checks, including Thaana and logical CSS). Use Thaana in tests of user-facing behaviour. List uncertain translations in `docs/advanced-usage/dhivehi-and-rtl.md`.
- Every change needs tests, docs (linked from `llms.txt`, checked by `DocsTest`), the Boost skill if usage changes, and a line in `CHANGELOG.md`.
- Work on `main`. Never tag or release.

## Commands

```bash
composer install && pnpm install      # pnpm 10.28 (packageManager); composer first (stencil's and form-builder's React code is in vendor/)
composer lint && composer test        # Pint and PHPUnit
pnpm lint && pnpm format:check && pnpm typecheck && pnpm test
pnpm build                            # dist/
pnpm examples                         # http://localhost:5173
```
