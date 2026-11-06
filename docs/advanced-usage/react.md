---
title: React
sidebar_position: 7
---

Apps import the package's React code as `@javaabu/workflows`, through javaabu/manifest's Vite plugin.

```tsx
import { WorkflowBuilder, WorkflowGraph, type BuilderData } from "@javaabu/workflows"

export default function LeaveWorkflow({ builder, strings, urls }: { builder: BuilderData; strings: Record<string, string>; urls: { save: string } }) {
  return <WorkflowBuilder builder={builder} strings={strings} saveUrl={urls.save} />
}
```

| Export | |
| --- | --- |
| `WorkflowBuilder` | the builder: `builder` (`BuilderData::builder()`), `strings`, `saveUrl` (none: read-only), `csrf`, `onSave(definition)` to save yourself, `onChange(definition)` |
| `WorkflowGraph` | the diagram, read-only: `definition`, `catalog`, `strings`, `highlight` (step keys in order) |
| `Canvas`, `Inspector`, `ListView` | the builder's parts |
| `AssigneesEditor`, `ConditionsEditor`, `ActionsEditor` | the inspector's editors |
| `addStep`, `addTransition`, `removeStep`, `removeTransition`, `updateStep`, `updateTransition`, `moveStep`, `layout`, `edgePath`, `resolveText`, `setText`, `uniqueKey`, `stepName` | pure functions over a `Definition` |
| `saveDefinition(url, definition, csrf?)` | PUTs a definition and returns its problems |
| `InstancesTable`, `History`, `SimulationResult`, `InstanceStatus`, `WorkflowStatus`, `PageHeader` | pieces of the pages |
| `WorkflowsIndex`, `WorkflowsEdit`, `WorkflowsShow`, `WorkflowsVersions`, `WorkflowsTest`, `InstancesIndex`, `InstancesShow`, `TasksIndex`, `StartShow` | the Inertia pages |
| types | `Definition`, `StepDef`, `TransitionDef`, `Catalog`, `BuilderData`, and every page's props |

The pages use `@javaabu/forms` (set `router={inertia}` on its `FormsProvider`) and stencil's React renderer. Step forms and entries render with form-builder's stencil components.

# Blade

`workflows::builder` mounts the builder on `[data-workflow-builder]` and the diagram on `[data-workflow-graph]`, from their JSON `data-props`; mount ones added later with `window.JavaabuWorkflows.mount(root)`. It also asks before submitting forms with `data-confirm`. It's prebuilt with React, so Blade apps need no React of their own.

The builder's styles are `workflows::styles` (`resources/css/workflows.css`), with logical properties only. They use your theme's `--border`, `--muted`, `--primary`, … variables when it has them.
