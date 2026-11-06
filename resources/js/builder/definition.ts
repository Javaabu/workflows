import type { Definition, Position, StepDef, StepType, Text, TransitionDef } from "../types"

/** The size of a step on the canvas */
export const NODE_WIDTH = 176
export const NODE_HEIGHT = 64

/** Text in a locale: the locale's, the first locale's, then any */
export function resolveText(text: Text, locale?: string, fallback?: string): string {
  if (typeof text === "string") return text || fallback || ""
  if (text && typeof text === "object") {
    const values = Object.values(text).filter((value) => value !== "")
    return (locale && text[locale]) || values[0] || fallback || ""
  }
  return fallback ?? ""
}

/** Set one locale's text, keeping a plain string when there's one locale */
export function setText(text: Text, value: string, locale: string, locales: string[]): Text {
  if (locales.length <= 1) return value || undefined

  const map: Record<string, string> = typeof text === "string" ? { [locales[0] ?? locale]: text } : { ...(text ?? {}) }
  if (value === "") delete map[locale]
  else map[locale] = value

  return Object.keys(map).length === 0 ? undefined : map
}

/** A key that isn't taken: `review`, `review_2`, … */
export function uniqueKey(base: string, taken: string[]): string {
  const clean =
    base
      .toLowerCase()
      .normalize("NFKD")
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "")
      .replace(/^([0-9])/, "s_$1") || "step"

  if (!taken.includes(clean)) return clean

  let number = 2
  while (taken.includes(`${clean}_${number}`)) number++
  return `${clean}_${number}`
}

export function stepName(step: StepDef, locale?: string): string {
  return resolveText(step.name, locale, step.key)
}

export function getStep(definition: Definition, key: string): StepDef | undefined {
  return definition.steps.find((step) => step.key === key)
}

export function transitionsFrom(definition: Definition, key: string): TransitionDef[] {
  return definition.transitions.filter((transition) => transition.from === key)
}

/** Where a new step goes: to the end of the row of the selected step, or after the rest */
export function nextPosition(definition: Definition, near?: string): Position {
  const anchor = near ? getStep(definition, near)?.position : undefined
  if (anchor) return { x: anchor.x + NODE_WIDTH + 64, y: anchor.y }

  const xs = definition.steps.map((step) => step.position?.x ?? 0)
  return { x: definition.steps.length === 0 ? 40 : Math.max(...xs) + NODE_WIDTH + 64, y: 160 }
}

export function addStep(
  definition: Definition,
  type: StepType,
  name: string,
  near?: string,
): { definition: Definition; key: string } {
  const key = uniqueKey(
    type === "start" ? "start" : name || type,
    definition.steps.map((step) => step.key),
  )
  const step: StepDef = { key, type, name, position: nextPosition(definition, near) }
  if (type === "end") step.outcome = key

  return { definition: { ...definition, steps: [...definition.steps, step] }, key }
}

/** Remove a step and every transition to or from it */
export function removeStep(definition: Definition, key: string): Definition {
  return {
    ...definition,
    steps: definition.steps.filter((step) => step.key !== key),
    transitions: definition.transitions.filter((transition) => transition.from !== key && transition.to !== key),
  }
}

export function updateStep(definition: Definition, key: string, changes: Partial<StepDef>): Definition {
  const renamed = changes.key !== undefined && changes.key !== key

  return {
    ...definition,
    steps: definition.steps.map((step) => (step.key === key ? clean({ ...step, ...changes }) : step)),
    transitions: renamed
      ? definition.transitions.map((transition) => ({
          ...transition,
          from: transition.from === key ? changes.key! : transition.from,
          to: transition.to === key ? changes.key! : transition.to,
        }))
      : definition.transitions,
  }
}

export function moveStep(definition: Definition, key: string, position: Position): Definition {
  return updateStep(definition, key, {
    position: { x: Math.max(0, Math.round(position.x)), y: Math.max(0, Math.round(position.y)) },
  })
}

/** Connect two steps. Returns null when they can't be connected (an end step, into the start, itself when automatic). */
export function addTransition(
  definition: Definition,
  from: string,
  to: string,
  label?: string,
): { definition: Definition; key: string } | null {
  const source = getStep(definition, from)
  const target = getStep(definition, to)
  if (!source || !target || source.type === "end" || target.type === "start") return null
  if (from === to && source.type !== "task") return null

  const key = uniqueKey(
    source.type === "task" ? label || `to_${to}` : `${from}_to_${to}`,
    definition.transitions.map((transition) => transition.key),
  )
  const transition: TransitionDef = { key, from, to }
  if (source.type === "task") transition.label = label || resolveText(target.name, undefined, to)

  return { definition: { ...definition, transitions: [...definition.transitions, transition] }, key }
}

export function removeTransition(definition: Definition, key: string): Definition {
  return { ...definition, transitions: definition.transitions.filter((transition) => transition.key !== key) }
}

export function updateTransition(definition: Definition, key: string, changes: Partial<TransitionDef>): Definition {
  return {
    ...definition,
    transitions: definition.transitions.map((transition) =>
      transition.key === key ? clean({ ...transition, ...changes }) : transition,
    ),
  }
}

/** Drop empty values, as the server does */
function clean<T extends object>(value: T): T {
  const result: Record<string, unknown> = {}
  for (const [key, item] of Object.entries(value)) {
    if (item === undefined || item === null || item === "") continue
    if (Array.isArray(item) && item.length === 0) continue
    result[key] = item
  }
  return result as T
}

/** The canvas size that fits every step */
export function bounds(definition: Definition): { width: number; height: number } {
  const xs = definition.steps.map((step) => (step.position?.x ?? 0) + NODE_WIDTH)
  const ys = definition.steps.map((step) => (step.position?.y ?? 0) + NODE_HEIGHT)
  return { width: Math.max(640, ...xs) + 80, height: Math.max(360, ...ys) + 80 }
}

/** Steps without a position get one, left to right by distance from the start */
export function layout(definition: Definition): Definition {
  if (definition.steps.every((step) => step.position)) return definition

  const depth = new Map<string, number>()
  const start = definition.steps.find((step) => step.type === "start")
  const queue: string[] = []
  if (start) {
    depth.set(start.key, 0)
    queue.push(start.key)
  }
  while (queue.length > 0) {
    const current = queue.shift()!
    for (const transition of transitionsFrom(definition, current)) {
      if (!depth.has(transition.to)) {
        depth.set(transition.to, (depth.get(current) ?? 0) + 1)
        queue.push(transition.to)
      }
    }
  }

  const rows = new Map<number, number>()
  return {
    ...definition,
    steps: definition.steps.map((step) => {
      if (step.position) return step
      const column = depth.get(step.key) ?? Math.max(0, ...depth.values()) + 1
      const row = rows.get(column) ?? 0
      rows.set(column, row + 1)
      return { ...step, position: { x: 40 + column * (NODE_WIDTH + 64), y: 60 + row * (NODE_HEIGHT + 76) } }
    }),
  }
}

/**
 * The path of an edge between two steps, from the side of one to the side of
 * the other, as an SVG cubic curve. A step's transition to itself loops above it.
 */
export function edgePath(from: Position, to: Position): { d: string; label: Position } {
  if (from.x === to.x && from.y === to.y) {
    const x = from.x + NODE_WIDTH / 2
    const y = from.y
    return { d: `M ${x - 24} ${y} C ${x - 40} ${y - 56}, ${x + 40} ${y - 56}, ${x + 24} ${y}`, label: { x, y: y - 48 } }
  }

  // Back along the same row: below both steps
  if (to.x < from.x + NODE_WIDTH / 2 && Math.abs(to.y - from.y) < NODE_HEIGHT) {
    const start = { x: from.x + NODE_WIDTH / 2, y: from.y + NODE_HEIGHT }
    const end = { x: to.x + NODE_WIDTH / 2, y: to.y + NODE_HEIGHT }
    const depth = Math.max(start.y, end.y) + 56
    return {
      d: `M ${start.x} ${start.y} C ${start.x} ${depth}, ${end.x} ${depth}, ${end.x} ${end.y}`,
      label: { x: (start.x + end.x) / 2, y: depth - 14 },
    }
  }

  const forward = to.x >= from.x + NODE_WIDTH / 2
  const start = forward
    ? { x: from.x + NODE_WIDTH, y: from.y + NODE_HEIGHT / 2 }
    : { x: from.x + NODE_WIDTH / 2, y: to.y > from.y ? from.y + NODE_HEIGHT : from.y }
  const end = forward
    ? { x: to.x, y: to.y + NODE_HEIGHT / 2 }
    : { x: to.x + NODE_WIDTH / 2, y: to.y > from.y ? to.y : to.y + NODE_HEIGHT }

  const bend = forward ? Math.max(40, (end.x - start.x) / 2) : 0
  const vertical = forward ? 0 : Math.max(40, Math.abs(end.y - start.y) / 2) * (to.y > from.y ? 1 : -1)
  const d = `M ${start.x} ${start.y} C ${start.x + bend} ${start.y + vertical}, ${end.x - bend} ${end.y - vertical}, ${end.x} ${end.y}`

  return { d, label: { x: (start.x + end.x) / 2, y: (start.y + end.y) / 2 } }
}
