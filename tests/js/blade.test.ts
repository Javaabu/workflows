import { act } from "@testing-library/react"
import { describe, expect, it, vi } from "vitest"
import { mountBuilder, mountWorkflows } from "../../resources/js/blade/builder"
import show from "./fixtures/workflows-show.json"
import test from "./fixtures/workflows-test.json"

describe("the Blade entry", () => {
  it("mounts the builder and the diagram from data-props", async () => {
    document.body.innerHTML = `
      <div data-workflow-builder data-props='${JSON.stringify({ ...show, csrf: "token" }).replace(/'/g, "&#39;")}'><ol data-workflows-graph-list></ol></div>
      <div data-workflow-graph data-props='${JSON.stringify({ definition: test.definition, catalog: test.catalog, strings: test.strings, highlight: ["start", "review"] }).replace(/'/g, "&#39;")}'></div>
    `

    await act(async () => mountWorkflows())

    expect(document.querySelector("[data-workflow-builder-root]")).toBeInTheDocument()
    expect(document.querySelector("[data-workflows-graph-list]")).toBeNull()
    expect(document.querySelector("[data-workflow-graph-root] [data-step='review']")).toHaveAttribute(
      "data-highlighted",
    )
    expect(window.JavaabuWorkflows?.mount).toBe(mountWorkflows)

    // Mounting again keeps the same root
    const element = document.querySelector<HTMLElement>("[data-workflow-builder]")!
    expect(mountBuilder(element)).toBe(mountBuilder(element))
  })

  it("ignores elements without props", async () => {
    document.body.innerHTML = `<div data-workflow-builder data-props="nope"></div>`
    await act(async () => mountWorkflows())
    expect(document.querySelector("[data-workflow-builder-root]")).toBeNull()
  })

  it("asks before submitting forms with data-confirm", () => {
    document.body.innerHTML = `<form data-confirm="Sure?"><button type="submit">Go</button></form>`
    const confirm = vi.spyOn(window, "confirm").mockReturnValue(false)
    const event = new Event("submit", { bubbles: true, cancelable: true })

    document.querySelector("form")!.dispatchEvent(event)

    expect(confirm).toHaveBeenCalledWith("Sure?")
    expect(event.defaultPrevented).toBe(true)
  })
})
