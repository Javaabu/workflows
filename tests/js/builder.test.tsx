import { act, fireEvent, render, screen, within } from "@testing-library/react"
import { describe, expect, it, vi } from "vitest"
import { WorkflowBuilder, WorkflowGraph, type Definition, type WorkflowsShowProps } from "@javaabu/workflows"
import show from "./fixtures/workflows-show.json"

const props = show as unknown as WorkflowsShowProps

function renderBuilder(
  onSave = vi.fn(async () => ({ ok: true, message: "Draft saved.", errors: {}, unreachable: [] })),
) {
  const changes: Definition[] = []
  render(
    <WorkflowBuilder
      builder={props.builder}
      strings={props.strings}
      onSave={onSave}
      onChange={(definition) => changes.push(definition)}
    />,
  )
  return { onSave, changes }
}

const node = (key: string) => document.querySelector<HTMLButtonElement>(`[data-step="${key}"]`)!

describe("WorkflowBuilder", () => {
  it("draws every step and transition", () => {
    renderBuilder()

    const canvas = screen.getByRole("group", { name: "Workflow diagram" })
    expect(within(canvas).getByRole("button", { name: "Manager review, Task" })).toBeInTheDocument()
    expect(canvas.querySelectorAll("[data-step]")).toHaveLength(7)
    expect(canvas.querySelectorAll("path.workflows-edge")).toHaveLength(9)
    expect(
      within(canvas).getByRole("button", { name: /Transition: Approve \(review → long_leave\)/ }),
    ).toBeInTheDocument()
    expect(within(canvas).getByRole("button", { name: /subject.days > 5/ })).toBeInTheDocument()
  })

  it("selects a step with the keyboard and edits it in the inspector", () => {
    const { changes } = renderBuilder()

    act(() => node("review").focus())
    fireEvent.click(node("review"), { detail: 0 })

    const inspector = screen.getByRole("complementary", { name: "Step" })
    expect(within(inspector).getByRole("heading", { name: "Manager review" })).toBeInTheDocument()
    expect(node("review")).toHaveAttribute("aria-pressed", "true")
    expect(document.querySelector("[data-announcer]")).toHaveTextContent("Manager review selected.")

    fireEvent.change(within(inspector).getByLabelText("Due in (hours)"), { target: { value: "24" } })
    expect(changes.at(-1)?.steps.find((step) => step.key === "review")?.due_in).toBe(24)
    expect(screen.getByRole("status")).toHaveTextContent("Unsaved changes")
  })

  it("moves steps with the arrow keys and announces it", () => {
    const { changes } = renderBuilder()
    const before = props.builder.definition.steps.find((step) => step.key === "review")!.position!

    fireEvent.keyDown(node("review"), { key: "ArrowRight" })
    fireEvent.keyDown(node("review"), { key: "ArrowDown", shiftKey: true })

    expect(changes.at(-1)?.steps.find((step) => step.key === "review")?.position).toEqual({
      x: before.x + 8,
      y: before.y + 40,
    })
    expect(document.querySelector("[data-announcer]")).toHaveTextContent("Manager review moved.")
  })

  it("adds a step after the selected one, connected to it", () => {
    const { changes } = renderBuilder()

    fireEvent.click(node("approved"), { detail: 0 })
    fireEvent.click(node("hr_review"), { detail: 0 })
    fireEvent.change(screen.getByLabelText("Type", { selector: "#workflows-step-type" }), {
      target: { value: "action" },
    })
    fireEvent.click(screen.getByRole("button", { name: "Add a step" }))

    const last = changes.at(-1)!
    expect(last.steps.at(-1)).toMatchObject({ key: "automatic_action", type: "action" })
    expect(last.transitions.at(-1)).toMatchObject({ from: "hr_review", to: "automatic_action" })
    expect(node("automatic_action")).toBeInTheDocument()
  })

  it("connects steps from the inspector and deletes with the keyboard", () => {
    const { changes } = renderBuilder()

    fireEvent.click(node("revise"), { detail: 0 })
    const inspector = screen.getByRole("complementary")
    fireEvent.change(within(inspector).getByLabelText("Go to"), { target: { value: "rejected" } })
    fireEvent.click(within(inspector).getByRole("button", { name: "Add a transition" }))

    expect(changes.at(-1)?.transitions.at(-1)).toMatchObject({ from: "revise", to: "rejected", label: "Rejected" })
    expect(screen.getByRole("complementary", { name: "Transition" })).toBeInTheDocument()

    fireEvent.keyDown(node("revise"), { key: "Delete" })
    expect(changes.at(-1)?.steps.some((step) => step.key === "revise")).toBe(false)
    expect(
      changes.at(-1)?.transitions.some((transition) => transition.from === "revise" || transition.to === "revise"),
    ).toBe(false)
    expect(document.querySelector("[data-announcer]")).toHaveTextContent("Revise deleted.")
  })

  it("connects steps by dragging from a handle", () => {
    const { changes } = renderBuilder()
    const handle = document.querySelector<HTMLElement>('[data-handle="revise"]')!

    document.elementFromPoint = vi.fn(() => node("approved"))
    fireEvent.pointerDown(handle, { clientX: 10, clientY: 10, pointerId: 1 })
    fireEvent.pointerUp(handle, { clientX: 500, clientY: 100, pointerId: 1 })

    expect(changes.at(-1)?.transitions.at(-1)).toMatchObject({ from: "revise", to: "approved" })
  })

  it("edits a transition's conditions", () => {
    const { changes } = renderBuilder()

    fireEvent.click(screen.getByRole("button", { name: /Transition: subject.days > 5/ }))
    const inspector = screen.getByRole("complementary", { name: "Transition" })
    const condition = inspector.querySelector<HTMLElement>("[data-condition='0']")!

    fireEvent.change(within(condition).getByLabelText("Value"), { target: { value: "10" } })
    expect(changes.at(-1)?.transitions.find((transition) => transition.key === "long")?.conditions?.rules[0]).toEqual({
      field: "subject.days",
      operator: ">",
      value: 10,
    })

    fireEvent.change(within(condition).getByLabelText("Operator"), { target: { value: "in" } })
    fireEvent.change(within(condition).getByLabelText("Value"), { target: { value: "annual, sick" } })
    expect(
      changes.at(-1)?.transitions.find((transition) => transition.key === "long")?.conditions?.rules[0]?.value,
    ).toEqual(["annual", "sick"])
  })

  it("adds an action with its settings", () => {
    const { changes } = renderBuilder()

    fireEvent.click(node("approved"), { detail: 0 })
    const inspector = screen.getByRole("complementary")
    fireEvent.change(within(inspector).getByLabelText("Add an action"), { target: { value: "notify" } })
    fireEvent.click(within(inspector.querySelector("[data-actions]")!).getByRole("button", { name: "Add" }))

    const action = inspector.querySelector<HTMLElement>("[data-action='notify']")!
    fireEvent.click(within(action).getByRole("button", { name: "Add an assignee" }))
    fireEvent.change(within(action).getByLabelText("Type"), { target: { value: "assignees" } })
    fireEvent.change(within(action).getByLabelText("Title"), { target: { value: "Approved: {{ subject.title }}" } })

    expect(changes.at(-1)?.steps.find((step) => step.key === "approved")?.actions).toEqual([
      { action: "notify", settings: { to: [{ type: "assignees" }], title: "Approved: {{ subject.title }}" } },
    ])
  })

  it("saves the draft and shows its problems", async () => {
    const onSave = vi.fn(async () => ({
      ok: true,
      message: "Draft saved.",
      errors: { "steps.revise": ["Nothing leaves revise: add a transition from it."] },
      unreachable: [],
    }))
    renderBuilder(onSave)

    const save = screen.getByRole("button", { name: "Save draft" })
    expect(save).toBeDisabled()

    fireEvent.keyDown(node("review"), { key: "ArrowUp" })
    await act(async () => {
      fireEvent.click(save)
    })

    expect(onSave).toHaveBeenCalledOnce()
    expect(screen.getByRole("status")).toHaveTextContent("Draft saved.")
    expect(node("revise")).toHaveAttribute("data-problem")

    fireEvent.click(screen.getByRole("button", { name: "Nothing leaves revise: add a transition from it." }))
    expect(node("revise")).toHaveAttribute("aria-pressed", "true")
  })

  it("saves with Ctrl+S over HTTP", async () => {
    const fetch = vi.fn(
      async () =>
        new Response(JSON.stringify({ message: "Draft saved.", errors: {}, unreachable: [] }), { status: 200 }),
    )
    vi.stubGlobal("fetch", fetch)
    render(
      <WorkflowBuilder
        builder={props.builder}
        strings={props.strings}
        saveUrl="/workflows/leave-request/definition"
        csrf="token"
      />,
    )

    fireEvent.keyDown(node("review"), { key: "ArrowUp" })
    await act(async () => {
      fireEvent.keyDown(node("review"), { key: "s", ctrlKey: true })
    })

    expect(fetch).toHaveBeenCalledWith(
      "/workflows/leave-request/definition",
      expect.objectContaining({ method: "PUT" }),
    )
    const init = (fetch.mock.calls[0] as unknown as [string, RequestInit])[1]
    expect((init.headers as Record<string, string>)["X-CSRF-TOKEN"]).toBe("token")
    expect(JSON.parse(init.body as string).definition.steps).toHaveLength(7)
    vi.unstubAllGlobals()
  })

  it("has a list view", () => {
    renderBuilder()

    fireEvent.click(screen.getByRole("button", { name: "Steps and transitions as a list" }))
    const list = screen.getByRole("list", { name: "Steps" })
    fireEvent.click(within(list).getByRole("button", { name: "Approve → Long leave?" }))

    expect(screen.getByRole("complementary", { name: "Transition" })).toBeInTheDocument()
  })

  it("is read-only without a save URL", () => {
    render(<WorkflowBuilder builder={props.builder} strings={props.strings} />)

    expect(screen.queryByRole("button", { name: "Add a step" })).not.toBeInTheDocument()
    expect(screen.queryByRole("button", { name: "Save draft" })).not.toBeInTheDocument()
    expect(document.querySelector("[data-handle]")).toBeNull()
  })
})

describe("WorkflowGraph", () => {
  it("highlights a path in order", () => {
    render(
      <WorkflowGraph
        definition={props.builder.definition}
        catalog={props.builder.catalog}
        strings={props.strings}
        highlight={["start", "review", "long_leave", "approved"]}
      />,
    )

    expect(node("review")).toHaveAttribute("data-highlighted")
    expect(node("hr_review")).not.toHaveAttribute("data-highlighted")
    expect(document.querySelector('[data-transition="approve"]')).toHaveAttribute("data-highlighted")
    expect(document.querySelector('[data-transition="long"]')).not.toHaveAttribute("data-highlighted")
  })
})
