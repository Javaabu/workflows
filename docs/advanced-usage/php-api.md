---
title: PHP API
sidebar_position: 8
---

# Workflows (facade) / WorkflowsManager

| Method | |
| --- | --- |
| `define(string $key): WorkflowDefinition` | a fluent definition |
| `sync(WorkflowDefinition $definition, ?bool $publish = null): Workflow` | validate and save it (idempotent) |
| `workflow(string $key): Workflow` | by key (`WorkflowException` if none) |
| `start($workflow, ?Model $subject, ?Model $by, Request\|array $data, ?string $comment): WorkflowInstance` | |
| `transition(WorkflowInstance $instance, string $transition, ?Model $by, Request\|array $data, ?string $comment, bool $force = false)` | |
| `cancel(WorkflowInstance $instance, ?Model $by, ?string $reason)` | |
| `simulate($workflow, array $data, array $choices, Model\|array\|null $subject, Model\|array\|null $starter, bool $draft): Simulation` | the tester |
| `registerAction($actions)`, `forgetAction($key)`, `hasAction($key)`, `action($key)`, `actions()` | actions |
| `assignee(string $type, callable\|string $resolver, ?string $label)`, `hasAssigneeResolver()`, `resolveAssignee()`, `customAssignees()` | assignee types |
| `instanceUrl(WorkflowInstance $instance)`, `instanceUrlUsing(?Closure $resolver)` | where notifications link |
| `text(string\|array\|null $text, ?string $locale)` | text from a definition in a locale |
| `model(string $name)` | `workflow`, `version`, `instance`, `task`, `assignee`, `history` |
| `engine(): Engine`, `validator(): DefinitionValidator` | |
| `WorkflowsManager::ignoreMigrations()` | run your own migrations |

# Definitions (`Javaabu\Workflows\Definitions`)

- `WorkflowDefinition`: `make($key)`, `name()`, `description()`, `for($model)`, `locked()`, `steps([...])`, `step()`, `transitions([...])`, `transition()`, `sync()`, `getStep()`, `getStartStep()`, `getTransition()`, `transitionsFrom($step, $defaults_last = true)`, `transitionsTo()`, `structure()`, `toArray()`, `fromArray($array, $key = null)`.
- `Step`: `start()`, `task()`, `decision()`, `action()`, `end($key, $outcome)`, `make($key, $type)`, `name()`, `description()`, `assignTo(...)`, `form($key, $version)`, `runs($action, $settings)`, `outcome()`, `color()`, `dueIn($hours)`, `position($x, $y)`, getters, `isAutomatic()`, `isTask()`, `isStart()`, `isEnd()`.
- `Transition`: `make($key)`, `from()`, `to()`, `label()`, `color()`, `when($field, $operator, $value)`, `whenAny()`, `conditions(Conditions)`, `comment('none'|'optional'|'required')`, `requireComment()`, `skipForm()`, `runs()`, getters.
- `Assignee`: `starter()`, `user($model|'alias:key')`, `role()`, `permission()`, `subject($attribute)`, `step($key)`, `make($type, $value)`.
- `Condition`, `Conditions` (`ALL`, `ANY`), `ActionCall`.
- `DefinitionValidator`: `errors($definition)`, `validate($definition)`, `unreachable($definition)`.

# Models (`Javaabu\Workflows\Models`)

- `Workflow`: `versions`, `publishedVersion`, `draftVersion`, `instances`, scopes `published()`, `for($subject)`; `isPublished()`, `canStart()`, `hasDraft()`, `subjectClass()`, `currentVersion()`, `draft($author)`, `publish($author)`, `discardDraft()`, `definition($draft = false)`, `text($attribute)`.
- `WorkflowVersion`: `definition` (array), `number`, `published_at`, `author`, `instances`; `definition($key)`, `replaceStructure($definition, $validate = true)`, `isDraft()`, `isPublished()`, `hashOf()`.
- `WorkflowInstance`: `workflow`, `version`, `subject`, `starter`, `tasks`, `history`, `status`, `outcome`, `current_step`, `data`; scopes `active()`, `completed()`, `forSubject($model)`, `ofWorkflow($key)`; `definition()`, `currentStep()`, `currentTask()`, `openTasks()`, `answer($key)`, `availableTransitions($user)`, `canAct($user)`, `transition()`, `cancel()`, `isActive()`, `isCompleted()`, `isCancelled()`.
- `WorkflowTask`: `instance`, `assignees`, `completedBy`, `entry`, `step`, `status`, `transition`, `comment`, `due_at`; scopes `open()`, `assignedTo($user)`, `overdue()`; `isAssignedTo($user)`, `isOverdue()`, `definition()`.
- `WorkflowTaskAssignee`: `kind` (user, role, permission), `assignee`, `value`; `matches($user)`, `label()`.
- `WorkflowHistory`: `event`, `step`, `from_step`, `transition`, `action`, `actor`, `comment`, `data`, `task`.

# The engine (`Javaabu\Workflows\Engine`)

- `Engine`: `start()`, `transition()`, `cancel()`, `availableTransitions()`, `canAct($task, $user)`, `pick($scope, $definition, $step)`.
- `Simulator` / `Simulation`: `status`, `path`, `data`, `outcome`, `message`, `options`, `steps()`, `transitions()`, `lastStep()`, `completed()`, `waiting()`, `toArray()`.
- `ConditionEvaluator`: `passes($conditions, $scope)`, `rule($condition, $scope)`.
- `DataResolver`: `get($scope, $path)`, `interpolate($scope, $value)`.
- `AssigneeResolver`: `resolve($assignee, $instance)`, `users($assignee, $instance)`.
- `StepForms`: `version($step)`, `stencil($step, $action, $values)`, `submit($step, $input, $submitter, $subject)`.

# Other

- `Concerns\HasWorkflows`: `workflowInstances()`, `workflowInstance($key)`, `startWorkflow($workflow, $by, $data, $comment)`, `hasActiveWorkflow($key)`.
- `Actions\Action`, `Actions\ActionContext`, `Actions\ActionRunner`, `Jobs\RunWorkflowAction`, the built-in actions in `Actions\BuiltIn`.
- `Events\*` (see [Running instances](../basic-usage/running-instances.md#events)), `Exceptions\WorkflowException`.
- `Routing\WorkflowRoutes`, `Builder\BuilderData` (the pages' data), `Builder\WorkflowForms`, `Views\ViewRenderer`, `Support\Strings`, `Support\Translations`, `Support\Text`, `Assets\Assets`.
- `Policies\WorkflowPolicy` (permission constants) and `Policies\WorkflowInstancePolicy`.
- `Ai\WorkflowAssistant`, `Ai\WorkflowAssistantAgent` (with laravel/ai).
