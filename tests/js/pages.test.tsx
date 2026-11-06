import { fireEvent, render, screen, within } from "@testing-library/react"
import { beforeEach, describe, expect, it, vi } from "vitest"
import {
  InstancesIndex,
  InstancesShow,
  StartShow,
  TasksIndex,
  WorkflowsEdit,
  WorkflowsIndex,
  WorkflowsShow,
  WorkflowsTest,
  WorkflowsVersions,
  type InstancesIndexProps,
  type InstancesShowProps,
  type StartShowProps,
  type TasksIndexProps,
  type WorkflowsEditProps,
  type WorkflowsIndexProps,
  type WorkflowsShowProps,
  type WorkflowsTestProps,
  type WorkflowsVersionsProps,
} from "@javaabu/workflows"
import instancesIndex from "./fixtures/instances-index.json"
import instancesShow from "./fixtures/instances-show.json"
import startShow from "./fixtures/start-show.json"
import tasksIndex from "./fixtures/tasks-index.json"
import workflowsEdit from "./fixtures/workflows-edit.json"
import workflowsIndex from "./fixtures/workflows-index.json"
import workflowsShow from "./fixtures/workflows-show.json"
import workflowsTest from "./fixtures/workflows-test.json"
import workflowsVersions from "./fixtures/workflows-versions.json"

const router = vi.hoisted(() => ({ put: vi.fn(), post: vi.fn(), delete: vi.fn(), get: vi.fn(), patch: vi.fn() }))

vi.mock("@inertiajs/react", () => ({ router, usePage: () => ({ props: {} }) }))

beforeEach(() => {
  for (const method of Object.values(router)) method.mockReset()
  vi.unstubAllGlobals()
})

describe("workflows::Workflows/Index", () => {
  it("lists workflows", () => {
    render(<WorkflowsIndex {...(workflowsIndex as unknown as WorkflowsIndexProps)} />)

    expect(screen.getByRole("heading", { name: "Workflows" })).toBeInTheDocument()
    expect(screen.getByRole("link", { name: "Leave request" })).toHaveAttribute(
      "href",
      "http://localhost/workflows/leave-request",
    )
    expect(screen.getByText("Published")).toBeInTheDocument()
    expect(screen.getByRole("link", { name: "New workflow" })).toBeInTheDocument()
  })
})

describe("workflows::Workflows/Show", () => {
  it("renders the builder with the page's actions", () => {
    render(<WorkflowsShow {...(workflowsShow as unknown as WorkflowsShowProps)} />)

    expect(screen.getByRole("heading", { name: "Leave request", level: 1 })).toBeInTheDocument()
    expect(screen.getByRole("group", { name: "Workflow diagram" })).toBeInTheDocument()
    expect(screen.getByRole("link", { name: "Test" })).toHaveAttribute(
      "href",
      "http://localhost/workflows/leave-request/test",
    )
    expect(screen.getByRole("button", { name: "Save draft" })).toBeInTheDocument()
    expect(screen.queryByRole("button", { name: "Publish" })).not.toBeInTheDocument()
  })
})

describe("workflows::Workflows/Edit and Versions", () => {
  it("renders the settings form", () => {
    render(<WorkflowsEdit {...(workflowsEdit as unknown as WorkflowsEditProps)} />)

    expect(screen.getByRole("heading", { name: "Settings" })).toBeInTheDocument()
    expect(screen.getByLabelText(/^Key/)).toHaveValue("leave-request")
  })

  it("lists versions", () => {
    render(<WorkflowsVersions {...(workflowsVersions as unknown as WorkflowsVersionsProps)} />)

    expect(screen.getByRole("heading", { name: "Versions" })).toBeInTheDocument()
    expect(document.querySelector('[data-version="1"]')).toHaveTextContent("Current")
  })
})

describe("workflows::Workflows/Test", () => {
  const props = workflowsTest as unknown as WorkflowsTestProps

  it("shows the last result and the path on the diagram", () => {
    render(<WorkflowsTest {...props} />)

    expect(document.querySelector("[data-simulation-status='waiting']")).toBeInTheDocument()
    expect(screen.getByRole("list", { name: "Path" }).querySelectorAll("li[data-path-step]")).toHaveLength(4)
    expect(document.querySelector('[data-step="hr_review"]')).toHaveAttribute("data-highlighted")
  })

  it("runs again over JSON", async () => {
    const simulation = {
      ...props.simulation!,
      status: "completed",
      outcome: "approved",
      message: null,
      path: props.simulation!.path.slice(0, 2),
    }
    const fetch = vi.fn(async () => new Response(JSON.stringify({ simulation }), { status: 200 }))
    vi.stubGlobal("fetch", fetch)

    render(<WorkflowsTest {...props} />)
    fireEvent.change(screen.getByLabelText("Manager review"), { target: { value: "reject" } })
    fireEvent.click(screen.getByRole("button", { name: "Run the test" }))

    await screen.findByText("Completed")
    const body = JSON.parse((fetch.mock.calls[0] as unknown as [string, RequestInit])[1].body as string)
    expect(body.choices).toMatchObject({ review: "reject" })
  })
})

describe("workflows::Instances/Show", () => {
  const props = instancesShow as unknown as InstancesShowProps

  it("shows the task's form and a button per transition", () => {
    render(<InstancesShow {...props} />)

    const card = document.querySelector<HTMLElement>("[data-workflows-act]")!
    expect(within(card).getByLabelText(/Approved days/)).toBeInTheDocument()
    expect(within(card).getByLabelText("Comment")).toBeInTheDocument()

    const accept = within(card).getByRole("button", { name: "ގަބޫލު" })
    expect(accept).toHaveAttribute("name", "transition")
    expect(accept).toHaveAttribute("value", "accept")
    expect(accept).not.toHaveAttribute("formnovalidate")
    expect(within(card).getByRole("button", { name: "Decline" })).toHaveAttribute("formnovalidate")

    expect(document.querySelector("[data-workflows-entry='start']")).toBeInTheDocument()
    expect(screen.getByRole("heading", { name: "History" })).toBeInTheDocument()
  })

  it("posts the step's fields, the comment and the clicked transition in one form", () => {
    render(<InstancesShow {...props} />)

    const decline = screen.getByRole<HTMLButtonElement>("button", { name: "Decline" })
    const comment = screen.getByLabelText<HTMLTextAreaElement>("Comment")
    const days = screen.getByLabelText<HTMLInputElement>(/Approved days/)

    expect(decline.form).not.toBeNull()
    expect(decline.form).toHaveAttribute("action", props.urls.transition)
    expect(decline.form).toHaveAttribute("method", "POST")
    expect(comment.form).toBe(decline.form)
    expect(days.form).toBe(decline.form)
    expect(decline.type).toBe("submit")
  })
})

describe("workflows::Instances/Index and Tasks/Index", () => {
  it("lists a workflow's instances", () => {
    render(<InstancesIndex {...(instancesIndex as unknown as InstancesIndexProps)} />)

    expect(screen.getByRole("heading", { name: "Instances" })).toBeInTheDocument()
    expect(screen.getByRole("link", { name: "Open" })).toBeInTheDocument()
  })

  it("lists my tasks with tabs", () => {
    render(<TasksIndex {...(tasksIndex as unknown as TasksIndexProps)} />)

    expect(screen.getByRole("link", { name: "Assigned to me" })).toHaveAttribute("aria-current", "page")
    expect(screen.getByText("Review")).toBeInTheDocument()
  })
})

describe("workflows::Start/Show", () => {
  it("shows the start form", () => {
    render(<StartShow {...(startShow as unknown as StartShowProps)} />)

    expect(screen.getByLabelText(/Leave type/)).toBeInTheDocument()
    expect(screen.getByRole("button", { name: "Start" })).toBeInTheDocument()
  })
})
