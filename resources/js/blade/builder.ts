/**
 * The Blade entry (`workflows::builder`): mounts the builder on every
 * `[data-workflow-builder]` and the read-only diagram on every
 * `[data-workflow-graph]`, from their JSON `data-props`, as the Blade pages
 * render them. React is bundled in. It also asks before submitting forms with
 * `data-confirm`. Elements added later mount with `mountWorkflows(root)`.
 *
 * It's a .ts file (createElement, no JSX) registered with script(), not
 * react(): apps load the prebuilt file, so manifest:check doesn't ask Blade
 * apps for React's build packages. It imports no CSS: Blade pages load
 * `workflows::styles` themselves.
 */
import { createElement } from "react"
import { createRoot, type Root } from "react-dom/client"
import { WorkflowBuilder } from "../builder/WorkflowBuilder"
import { WorkflowGraph } from "../builder/WorkflowGraph"
import type { WorkflowsShowProps, WorkflowsTestProps } from "../types"

const roots = new WeakMap<HTMLElement, Root>()

function props<T>(element: HTMLElement): T | null {
  try {
    return JSON.parse(element.dataset.props ?? "") as T
  } catch {
    return null
  }
}

export function mountBuilder(element: HTMLElement): Root | null {
  const existing = roots.get(element)
  if (existing) return existing

  const data = props<WorkflowsShowProps & { csrf?: string }>(element)
  if (!data) return null

  const root = createRoot(element)
  root.render(
    createElement(WorkflowBuilder, {
      builder: data.builder,
      strings: data.strings,
      saveUrl: data.can.update ? data.urls.save : undefined,
      csrf: data.csrf,
    }),
  )
  roots.set(element, root)
  return root
}

export function mountGraph(element: HTMLElement): Root | null {
  const existing = roots.get(element)
  if (existing) return existing

  const data = props<Pick<WorkflowsTestProps, "definition" | "catalog" | "strings"> & { highlight?: string[] }>(element)
  if (!data?.definition) return null

  const root = createRoot(element)
  root.render(
    createElement(WorkflowGraph, {
      definition: data.definition,
      catalog: data.catalog,
      strings: data.strings,
      highlight: data.highlight,
    }),
  )
  roots.set(element, root)
  return root
}

export function mountWorkflows(scope: ParentNode = document): void {
  scope.querySelectorAll<HTMLElement>("[data-workflow-builder]").forEach(mountBuilder)
  scope.querySelectorAll<HTMLElement>("[data-workflow-graph]").forEach(mountGraph)
}

/** Ask before submitting a form with data-confirm */
export function confirmForms(scope: Document = document): void {
  scope.addEventListener("submit", (event) => {
    const form = event.target as HTMLElement | null
    const question = form?.closest<HTMLElement>("[data-confirm]")?.dataset.confirm
    if (question && !window.confirm(question)) event.preventDefault()
  })
}

declare global {
  interface Window {
    JavaabuWorkflows?: {
      mount: typeof mountWorkflows
      mountBuilder: typeof mountBuilder
      mountGraph: typeof mountGraph
    }
  }
}

if (typeof window !== "undefined") {
  window.JavaabuWorkflows = { mount: mountWorkflows, mountBuilder, mountGraph }

  if (!(window as { __workflowsConfirm?: boolean }).__workflowsConfirm) {
    ;(window as { __workflowsConfirm?: boolean }).__workflowsConfirm = true
    confirmForms()
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => mountWorkflows())
  else mountWorkflows()
}
