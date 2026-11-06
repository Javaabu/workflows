import * as React from "react"
import type { Catalog, Definition, Position, StepDef, Strings, TransitionDef } from "../types"
import { NODE_HEIGHT, NODE_WIDTH, bounds, edgePath, resolveText, stepName } from "./definition"

export type Selection = { kind: "step"; key: string } | { kind: "transition"; key: string } | null

export interface CanvasProps {
  definition: Definition
  catalog: Pick<Catalog, "step_types">
  strings: Strings
  locale?: string
  selection?: Selection
  readOnly?: boolean
  /** Steps to highlight, e.g. the tester's path */
  highlight?: string[]
  /** Keys with problems: `steps.review`, `transitions.approve` */
  problems?: string[]
  zoom?: number
  onSelect?: (selection: Selection) => void
  /** A step moved: while dragging (commit false) and when it's dropped or moved by the keyboard (commit true) */
  onMove?: (key: string, position: Position, commit: boolean) => void
  onConnect?: (from: string, to: string) => void
  onDelete?: (selection: NonNullable<Selection>) => void
}

const STEP = 8
const BIG_STEP = 40

/** Whether the canvas is in a right-to-left context */
function useRtl(ref: React.RefObject<HTMLElement | null>): boolean {
  const [rtl, setRtl] = React.useState(false)
  React.useLayoutEffect(() => {
    const element = ref.current
    if (!element) return
    setRtl(getComputedStyle(element).direction === "rtl" || element.closest("[dir=rtl]") !== null)
  }, [ref])
  return rtl
}

function conditionSummary(transition: TransitionDef): string | null {
  const rules = transition.conditions?.rules ?? []
  if (rules.length === 0) return null
  const separator = transition.conditions?.match === "any" ? " / " : ", "
  return rules
    .map((rule) =>
      [rule.field, rule.operator, Array.isArray(rule.value) ? rule.value.join("|") : (rule.value ?? "")]
        .join(" ")
        .trim(),
    )
    .join(separator)
}

/**
 * The workflow diagram: steps are buttons you can tab to, move with the arrow
 * keys or drag, and connect by dragging from their handle; transitions are
 * curves with a label you can select. Positions are logical (from the inline
 * start), so the diagram mirrors in right-to-left pages.
 */
export function Canvas({
  definition,
  catalog,
  strings,
  locale,
  selection = null,
  readOnly = false,
  highlight = [],
  problems = [],
  zoom = 1,
  onSelect,
  onMove,
  onConnect,
  onDelete,
}: CanvasProps): React.JSX.Element {
  const container = React.useRef<HTMLDivElement>(null)
  const rtl = useRtl(container)
  const size = bounds(definition)
  const id = React.useId()
  const [connecting, setConnecting] = React.useState<{ from: string; to: Position } | null>(null)
  const drag = React.useRef<{ key: string; start: Position; origin: Position; moved: boolean } | null>(null)

  const types = React.useMemo(
    () => Object.fromEntries(catalog.step_types.map((type) => [type.type, type.label])),
    [catalog.step_types],
  )

  /** A pointer's position in canvas coordinates (logical: from the inline start) */
  const point = React.useCallback(
    (event: { clientX: number; clientY: number }): Position => {
      const box = container.current?.firstElementChild?.getBoundingClientRect()
      if (!box) return { x: 0, y: 0 }
      const x = rtl ? box.right - event.clientX : event.clientX - box.left
      return { x: x / zoom, y: (event.clientY - box.top) / zoom }
    },
    [rtl, zoom],
  )

  const startDrag = (event: React.PointerEvent, step: StepDef) => {
    if (readOnly || event.button !== 0) return
    ;(event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId)
    drag.current = { key: step.key, start: point(event), origin: step.position ?? { x: 0, y: 0 }, moved: false }
  }

  const moveDrag = (event: React.PointerEvent) => {
    const current = drag.current
    if (!current) return
    const now = point(event)
    const dx = now.x - current.start.x
    const dy = now.y - current.start.y
    if (!current.moved && Math.abs(dx) + Math.abs(dy) < 4) return
    current.moved = true
    onMove?.(current.key, { x: current.origin.x + dx, y: current.origin.y + dy }, false)
  }

  const endDrag = (event: React.PointerEvent, step: StepDef) => {
    const current = drag.current
    drag.current = null
    if (!current) return
    if (current.moved) {
      const now = point(event)
      onMove?.(
        step.key,
        { x: current.origin.x + now.x - current.start.x, y: current.origin.y + now.y - current.start.y },
        true,
      )
      return
    }
    onSelect?.({ kind: "step", key: step.key })
  }

  const startConnect = (event: React.PointerEvent, step: StepDef) => {
    if (readOnly) return
    event.stopPropagation()
    event.preventDefault()
    ;(event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId)
    setConnecting({ from: step.key, to: point(event) })
  }

  const moveConnect = (event: React.PointerEvent) => {
    if (!connecting) return
    setConnecting({ ...connecting, to: point(event) })
  }

  const endConnect = (event: React.PointerEvent) => {
    if (!connecting) return
    const target = document.elementFromPoint?.(event.clientX, event.clientY)?.closest<HTMLElement>("[data-step]")
    const to = target?.dataset.step
    setConnecting(null)
    if (to) onConnect?.(connecting.from, to)
  }

  const keyDown = (event: React.KeyboardEvent, step: StepDef) => {
    const position = step.position ?? { x: 0, y: 0 }
    const amount = event.shiftKey ? BIG_STEP : STEP
    const inline = rtl ? -1 : 1
    const moves: Record<string, Position> = {
      ArrowLeft: { x: position.x - amount * inline, y: position.y },
      ArrowRight: { x: position.x + amount * inline, y: position.y },
      ArrowUp: { x: position.x, y: position.y - amount },
      ArrowDown: { x: position.x, y: position.y + amount },
    }

    if (moves[event.key] && !readOnly) {
      event.preventDefault()
      onMove?.(step.key, moves[event.key]!, true)
      return
    }

    if ((event.key === "Delete" || event.key === "Backspace") && !readOnly) {
      event.preventDefault()
      onDelete?.({ kind: "step", key: step.key })
      return
    }

    if (event.key === "Escape") {
      onSelect?.(null)
    }
  }

  const positions = new Map(definition.steps.map((step) => [step.key, step.position ?? { x: 0, y: 0 }]))
  const edges = definition.transitions
    .map((transition) => {
      const from = positions.get(transition.from)
      const to = positions.get(transition.to)
      return from && to ? { transition, ...edgePath(from, to) } : null
    })
    .filter((edge) => edge !== null)

  const pathIndex = (key: string) => highlight.indexOf(key)
  const onPath = (transition: TransitionDef) => {
    const from = pathIndex(transition.from)
    return from >= 0 && highlight[from + 1] === transition.to
  }

  return (
    <div
      ref={container}
      className="workflows-canvas"
      role="group"
      aria-label={strings.canvas ?? "Workflow diagram"}
      aria-describedby={readOnly ? undefined : `${id}-help`}
      data-readonly={readOnly ? "" : undefined}
      onPointerMove={(event) => {
        moveDrag(event)
        moveConnect(event)
      }}
      onPointerUp={endConnect}
      onPointerDown={(event) => {
        if (event.target === event.currentTarget || (event.target as HTMLElement).dataset.canvasSurface !== undefined)
          onSelect?.(null)
      }}
    >
      {readOnly ? null : (
        <p id={`${id}-help`} className="workflows-sr-only">
          {strings.canvas_help}
        </p>
      )}
      <div
        className="workflows-canvas-surface"
        data-canvas-surface=""
        style={{ width: size.width * zoom, height: size.height * zoom }}
      >
        <div
          className="workflows-canvas-scale"
          style={{ width: size.width, height: size.height, transform: `scale(${zoom})` }}
        >
          <svg
            className="workflows-edges"
            width={size.width}
            height={size.height}
            aria-hidden="true"
            style={rtl ? { transform: "scaleX(-1)" } : undefined}
          >
            <defs>
              <marker
                id={`${id}-arrow`}
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="7"
                markerHeight="7"
                orient="auto-start-reverse"
              >
                <path d="M 0 0 L 10 5 L 0 10 z" className="workflows-arrow" />
              </marker>
            </defs>
            {edges.map(({ transition, d }) => (
              <path
                key={transition.key}
                d={d}
                className="workflows-edge"
                data-transition={transition.key}
                data-selected={selection?.kind === "transition" && selection.key === transition.key ? "" : undefined}
                data-highlighted={onPath(transition) ? "" : undefined}
                markerEnd={`url(#${id}-arrow)`}
              />
            ))}
            {connecting
              ? (() => {
                  const from = positions.get(connecting.from) ?? { x: 0, y: 0 }
                  return (
                    <line
                      className="workflows-edge-draft"
                      x1={from.x + NODE_WIDTH}
                      y1={from.y + NODE_HEIGHT / 2}
                      x2={connecting.to.x}
                      y2={connecting.to.y}
                    />
                  )
                })()
              : null}
          </svg>

          {edges.map(({ transition, label }) => {
            const text = resolveText(transition.label, locale) || conditionSummary(transition) || ""
            const selected = selection?.kind === "transition" && selection.key === transition.key
            const problem = problems.includes(`transitions.${transition.key}`)
            return (
              <button
                key={transition.key}
                type="button"
                className="workflows-edge-label"
                data-transition-label={transition.key}
                data-color={transition.color}
                data-selected={selected ? "" : undefined}
                data-problem={problem ? "" : undefined}
                aria-pressed={selected}
                style={{ insetInlineStart: label.x, top: label.y }}
                onClick={() => onSelect?.({ kind: "transition", key: transition.key })}
                onKeyDown={(event) => {
                  if ((event.key === "Delete" || event.key === "Backspace") && !readOnly) {
                    event.preventDefault()
                    onDelete?.({ kind: "transition", key: transition.key })
                  }
                }}
                aria-label={`${strings.transition ?? "Transition"}: ${text || transition.key} (${transition.from} → ${transition.to})`}
                dir="auto"
              >
                {text || "→"}
              </button>
            )
          })}

          {definition.steps.map((step) => {
            const position = step.position ?? { x: 0, y: 0 }
            const selected = selection?.kind === "step" && selection.key === step.key
            const problem = problems.includes(`steps.${step.key}`)
            const order = pathIndex(step.key)
            return (
              <div
                key={step.key}
                className="workflows-node-wrap"
                style={{ insetInlineStart: position.x, top: position.y, width: NODE_WIDTH, height: NODE_HEIGHT }}
              >
                <button
                  type="button"
                  className="workflows-node"
                  data-step={step.key}
                  data-type={step.type}
                  data-color={step.color}
                  data-selected={selected ? "" : undefined}
                  data-problem={problem ? "" : undefined}
                  data-highlighted={order >= 0 ? "" : undefined}
                  aria-pressed={selected}
                  aria-label={`${stepName(step, locale)}, ${types[step.type] ?? step.type}${problem ? ` (${strings.problems ?? "problem"})` : ""}`}
                  onPointerDown={(event) => startDrag(event, step)}
                  onPointerMove={moveDrag}
                  onPointerUp={(event) => endDrag(event, step)}
                  onKeyDown={(event) => keyDown(event, step)}
                  onClick={(event) => {
                    // Keyboard activation (Enter, Space); pointer clicks select in endDrag
                    if (event.detail === 0) onSelect?.({ kind: "step", key: step.key })
                  }}
                >
                  <span className="workflows-node-type">{types[step.type] ?? step.type}</span>
                  <span className="workflows-node-name" dir="auto">
                    {stepName(step, locale)}
                  </span>
                  {order >= 0 ? <span className="workflows-node-order">{order + 1}</span> : null}
                </button>
                {readOnly || step.type === "end" ? null : (
                  <span
                    className="workflows-handle"
                    data-handle={step.key}
                    title={strings.connect_help}
                    aria-hidden="true"
                    onPointerDown={(event) => startConnect(event, step)}
                    onPointerMove={moveConnect}
                    onPointerUp={endConnect}
                  />
                )}
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
