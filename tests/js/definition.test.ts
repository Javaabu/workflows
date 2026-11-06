import { describe, expect, it } from "vitest"
import {
  addStep,
  addTransition,
  edgePath,
  layout,
  moveStep,
  removeStep,
  resolveText,
  setText,
  uniqueKey,
  updateStep,
  type Definition,
} from "@javaabu/workflows"

const definition = (): Definition => ({
  workflows: 1,
  steps: [
    { key: "start", type: "start", position: { x: 40, y: 100 } },
    { key: "review", type: "task", name: { en: "Review", dv: "ރިވިއު" }, position: { x: 300, y: 100 } },
    { key: "done", type: "end", position: { x: 560, y: 100 } },
  ],
  transitions: [
    { key: "submit", from: "start", to: "review" },
    { key: "approve", from: "review", to: "done", label: "Approve" },
  ],
})

describe("definition helpers", () => {
  it("makes unique keys", () => {
    expect(uniqueKey("Manager review", [])).toBe("manager_review")
    expect(uniqueKey("review", ["review", "review_2"])).toBe("review_3")
    expect(uniqueKey("ޗުއްޓީ", [])).toBe("step")
    expect(uniqueKey("2nd", [])).toBe("s_2nd")
  })

  it("reads and writes text in locales", () => {
    expect(resolveText({ en: "Review", dv: "ރިވިއު" }, "dv")).toBe("ރިވިއު")
    expect(resolveText({ dv: "ރިވިއު" }, "en")).toBe("ރިވިއު")
    expect(resolveText(undefined, "en", "fallback")).toBe("fallback")
    expect(setText("Review", "ރިވިއު", "dv", ["en", "dv"])).toEqual({ en: "Review", dv: "ރިވިއު" })
    expect(setText({ en: "Review", dv: "ރިވިއު" }, "", "dv", ["en", "dv"])).toEqual({ en: "Review" })
    expect(setText("Review", "Check", "en", ["en"])).toBe("Check")
  })

  it("adds steps after the selected one", () => {
    const { definition: next, key } = addStep(definition(), "task", "HR review", "review")
    expect(key).toBe("hr_review")
    expect(next.steps.at(-1)?.position).toEqual({ x: 540, y: 100 })

    const end = addStep(definition(), "end", "Rejected")
    expect(end.definition.steps.at(-1)?.outcome).toBe("rejected")
  })

  it("only connects steps that can be connected", () => {
    expect(addTransition(definition(), "done", "review")).toBeNull()
    expect(addTransition(definition(), "review", "start")).toBeNull()
    expect(addTransition(definition(), "start", "start")).toBeNull()

    const result = addTransition(definition(), "review", "review")!
    expect(result.definition.transitions.at(-1)).toMatchObject({ from: "review", to: "review", label: "Review" })
  })

  it("removes a step with its transitions and renames keys everywhere", () => {
    expect(removeStep(definition(), "review").transitions).toEqual([])

    const renamed = updateStep(definition(), "review", { key: "check" })
    expect(renamed.transitions.map((transition) => [transition.from, transition.to])).toEqual([
      ["start", "check"],
      ["check", "done"],
    ])
  })

  it("keeps positions on the canvas", () => {
    expect(moveStep(definition(), "review", { x: -20, y: 10.6 }).steps[1]?.position).toEqual({ x: 0, y: 11 })
  })

  it("lays out steps without positions by distance from the start", () => {
    const laid = layout({
      workflows: 1,
      steps: [
        { key: "start", type: "start" },
        { key: "a", type: "task" },
        { key: "b", type: "task" },
        { key: "end", type: "end" },
      ],
      transitions: [
        { key: "1", from: "start", to: "a" },
        { key: "2", from: "start", to: "b" },
        { key: "3", from: "a", to: "end" },
      ],
    })
    expect(laid.steps.map((step) => step.position)).toEqual([
      { x: 40, y: 60 },
      { x: 280, y: 60 },
      { x: 280, y: 200 },
      { x: 520, y: 60 },
    ])
  })

  it("draws edges forward, back and to the same step", () => {
    expect(edgePath({ x: 0, y: 0 }, { x: 300, y: 0 }).d).toMatch(/^M 176 32 C/)
    expect(edgePath({ x: 300, y: 0 }, { x: 0, y: 0 }).d).toMatch(/^M 388 64 C 388 120/)
    expect(edgePath({ x: 0, y: 0 }, { x: 0, y: 0 }).label).toEqual({ x: 88, y: -48 })
  })
})
