# javaabu/workflows API

## Facade `Javaabu\Workflows\Facades\Workflows`

- `define($key)`, `sync($definition, ?bool $publish)`, `workflow($key)`
- `start($workflow, ?Model $subject, ?Model $by, Request|array $data = [], ?string $comment)`
- `transition($instance, $transition, ?Model $by, Request|array $data = [], ?string $comment, bool $force = false)`
- `cancel($instance, ?Model $by, ?string $reason)`
- `simulate($workflow, array $data = [], array $choices = [], Model|array|null $subject, Model|array|null $starter, bool $draft = false)`
- `registerAction()`, `forgetAction()`, `hasAction()`, `action()`, `actions()`
- `assignee($type, callable|string $resolver, ?string $label)`
- `instanceUrl($instance)`, `instanceUrlUsing(?Closure)`, `text($text, ?$locale)`, `model($name)`, `engine()`, `validator()`

## Definitions `Javaabu\Workflows\Definitions`

- `Step::start($key = 'start')`, `task($key)`, `decision($key)`, `action($key)`, `end($key, $outcome = $key)`; `->name()`, `->description()`, `->assignTo(Assignee ...)`, `->form($key, ?int $version)`, `->runs($action, array $settings)`, `->outcome()`, `->color()`, `->dueIn(int $hours)`, `->position($x, $y)`
- `Transition::make($key)->from()->to()->label()->color()->when($field, $operator, $value)->whenAny()->comment('none'|'optional'|'required')->requireComment()->skipForm()->runs()`
- `Assignee::starter()`, `user(Model|'alias:key')`, `role()`, `permission()`, `subject($attribute)`, `step($key)`, `make($type, $value)`
- Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `contains`, `filled`, `blank`
- `WorkflowDefinition::fromArray()`, `toArray()` (export), `structure()` (a version)
- `DefinitionValidator::errors()`, `validate()`, `unreachable()`

## Built-in actions

| Key | Settings |
| --- | --- |
| `notify` | `to` (assignee arrays, plus `{"type": "assignees"}`), `title`, `message` (text or locale maps), `channels` |
| `update_model` | `attributes` (fillable only) |
| `set_data` | `values` |
| `webhook` (queued) | `url`, `method`, `headers`, `payload`, `secret` (`X-Workflows-Signature: sha256=…`) |

## Models `Javaabu\Workflows\Models`

- `Workflow`: `publishedVersion`, `draftVersion`, `versions`, `instances`, `draft()`, `publish()`, `discardDraft()`, `definition($draft)`, `canStart()`, `text($attribute)`
- `WorkflowInstance`: `status` (`active`, `completed`, `cancelled`), `outcome`, `current_step`, `data`, `subject`, `starter`, `tasks`, `history`; `active()`, `ofWorkflow($key)`, `forSubject($model)`; `currentTask()`, `currentStep()`, `answer()`, `availableTransitions($user)`, `canAct($user)`, `transition()`, `cancel()`
- `WorkflowTask`: `open()`, `assignedTo($user)`, `overdue()`, `isAssignedTo()`, `entry` (form-builder), `completedBy`, `comment`, `transition`, `due_at`
- `WorkflowHistory`: events `started`, `entered`, `assigned`, `transitioned`, `action_ran`, `action_failed`, `completed`, `cancelled`

## Events `Javaabu\Workflows\Events` (after commit)

`WorkflowStarted`, `StepEntered`, `TaskCreated`, `TransitionTaken`, `WorkflowCompleted`, `WorkflowCancelled`, `ActionFailed` (straight away)

## Routes

- `Route::workflows([guard, prefix = 'workflows', name = 'workflows', middleware, controllers])`: `index`, `create`, `store`, `show` (builder), `edit`, `update`, `destroy`, `definition` (PUT JSON), `publish`, `draft.destroy`, `versions`, `export`, `test`, `test.run`, `instances.index`, `instances.show`, `instances.transition`, `instances.cancel`, `assistant.create`, `assistant`
- `Route::workflowTasks([guard, prefix = 'tasks', name = 'workflow-tasks', …])`: `index` (`?view=started`), `start` / `start.store` (`?subject=`), `instances.show`, `instances.transition` (`transition`, `comment`, form fields), `instances.cancel` (`reason`)

## Config `config/workflows.php`

`actions`, `actions_on_failure`, `notifications.javaabu`, `notifications.class`, `webhooks.timeout`, `max_automatic_steps`, `starters_can_cancel`, `sync.publish`, `models.*`, `tables.*`, `views`, `layout`, `layout_section`, `per_page`, `datetime_format`, `subjects`, `instance_route`, `controllers.*`, `policies.*`, `register_permissions`, `assistant.*`

## React `@javaabu/workflows`

`WorkflowBuilder` (`builder`, `strings`, `saveUrl`, `csrf`, `onSave`, `onChange`), `WorkflowGraph` (`definition`, `catalog`, `strings`, `highlight`), the pages (`WorkflowsShow`, `InstancesShow`, `TasksIndex`, …), definition helpers, types. Blade: `@manifestRequire('workflows::builder')` mounts `[data-workflow-builder]` and `[data-workflow-graph]`.
