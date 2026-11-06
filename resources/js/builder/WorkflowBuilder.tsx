import * as React from "react"
import type { BuilderData, Definition, Position, StepDef, StepType, Strings, TransitionDef } from "../types"
import { Canvas, type Selection } from "./Canvas"
import {
  addStep,
  addTransition,
  getStep,
  layout,
  moveStep,
  removeStep,
  removeTransition,
  resolveText,
  stepName,
  updateStep,
  updateTransition,
} from "./definition"
import { Inspector } from "./Inspector"
import { saveDefinition, type SaveResult } from "./save"

export interface WorkflowBuilderProps {
  builder: BuilderData
  strings: Strings
  /** Where the draft is saved (PUT, JSON); without it the builder is read-only */
  saveUrl?: string
  csrf?: string
  /** Replaces saving over HTTP, e.g. in tests or a custom client */
  onSave?: (definition: Definition) => Promise<SaveResult>
  onChange?: (definition: Definition) => void
}

function format(template: string | undefined, values: Record<string, string>): string {
  return Object.entries(values).reduce((text, [key, value]) => text.replace(`:${key}`, value), template ?? "")
}

/**
 * The visual builder: a diagram of the steps and transitions to drag, connect
 * and edit, with an inspector, a list view, keyboard control and announcements
 * for screen readers. It saves the draft's definition; the server checks it.
 */
export function WorkflowBuilder({
  builder,
  strings,
  saveUrl,
  csrf,
  onSave,
  onChange,
}: WorkflowBuilderProps): React.JSX.Element {
  const editable = builder.editable && (saveUrl !== undefined || onSave !== undefined)
  const [definition, setDefinition] = React.useState<Definition>(() => layout(builder.definition))
  const [selection, setSelection] = React.useState<Selection>(null)
  const [problems, setProblems] = React.useState({ errors: builder.errors, unreachable: builder.unreachable })
  const [dirty, setDirty] = React.useState(false)
  const [saving, setSaving] = React.useState(false)
  const [message, setMessage] = React.useState<string | null>(null)
  const [announcement, setAnnouncement] = React.useState("")
  const [zoom, setZoom] = React.useState(1)
  const [view, setView] = React.useState<"canvas" | "list">("canvas")
  const [type, setType] = React.useState<StepType>("task")
  const root = React.useRef<HTMLDivElement>(null)
  const locale = builder.catalog.locales[0]?.code

  const announce = (text: string) => setAnnouncement(text)

  const change = (next: Definition) => {
    setDefinition(next)
    setDirty(true)
    setMessage(null)
    onChange?.(next)
  }

  const label = (target: NonNullable<Selection>, current: Definition = definition) => {
    if (target.kind === "step") {
      const step = getStep(current, target.key)
      return step ? stepName(step, locale) : target.key
    }
    const transition = current.transitions.find((item) => item.key === target.key)
    return transition ? resolveText(transition.label, locale) || transition.key : target.key
  }

  const select = (next: Selection) => {
    setSelection(next)
    if (next) announce(format(strings.selected, { item: label(next) }))
  }

  const focusStep = (key: string) => {
    requestAnimationFrame(() => root.current?.querySelector<HTMLElement>(`[data-step="${CSS.escape(key)}"]`)?.focus())
  }

  const move = (key: string, position: Position, commit: boolean) => {
    const next = moveStep(definition, key, position)
    setDefinition(next)
    if (commit) {
      setDirty(true)
      onChange?.(next)
      announce(format(strings.moved, { step: label({ kind: "step", key }, next) }))
    }
  }

  const connect = (from: string, to: string) => {
    const result = addTransition(definition, from, to)
    if (!result) return
    change(result.definition)
    setSelection({ kind: "transition", key: result.key })
    announce(format(strings.added, { item: label({ kind: "transition", key: result.key }, result.definition) }))
  }

  const remove = (target: NonNullable<Selection>) => {
    const name = label(target)
    change(target.kind === "step" ? removeStep(definition, target.key) : removeTransition(definition, target.key))
    setSelection(null)
    announce(format(strings.deleted, { item: name }))
  }

  const add = () => {
    const name = builder.catalog.step_types.find((item) => item.type === type)?.label ?? strings.new_step ?? "Step"
    const near = selection?.kind === "step" ? selection.key : undefined
    const result = addStep(definition, type, name, near)
    let next = result.definition

    // A new step after the selected one is connected to it
    if (near) next = addTransition(next, near, result.key)?.definition ?? next

    change(next)
    setSelection({ kind: "step", key: result.key })
    announce(format(strings.added, { item: name }))
    focusStep(result.key)
  }

  const save = React.useCallback(async () => {
    if (!editable || saving) return
    setSaving(true)
    setMessage(strings.saving ?? "Saving…")
    try {
      const result = onSave ? await onSave(definition) : await saveDefinition(saveUrl!, definition, csrf)
      setProblems({ errors: result.errors, unreachable: result.unreachable })
      setMessage(result.message ?? (result.ok ? (strings.saved ?? "Saved") : null))
      if (result.ok) setDirty(false)
      announce(result.message ?? "")
    } catch (error) {
      setMessage(error instanceof Error ? error.message : String(error))
    } finally {
      setSaving(false)
    }
  }, [editable, saving, onSave, definition, saveUrl, csrf, strings.saving, strings.saved])

  React.useEffect(() => {
    if (!dirty) return
    const warn = (event: BeforeUnloadEvent) => event.preventDefault()
    window.addEventListener("beforeunload", warn)
    return () => window.removeEventListener("beforeunload", warn)
  }, [dirty])

  const keyDown = (event: React.KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "s") {
      event.preventDefault()
      void save()
    }
  }

  const problemKeys = Object.keys(problems.errors)

  return (
    <div ref={root} className="workflows-builder" data-workflow-builder-root="" onKeyDown={keyDown}>
      <div className="workflows-toolbar" role="toolbar" aria-label={strings.canvas}>
        {editable ? (
          <>
            <label className="workflows-sr-only" htmlFor="workflows-step-type">
              {strings.type}
            </label>
            <select
              id="workflows-step-type"
              className="workflows-input"
              value={type}
              onChange={(event) => setType(event.target.value as StepType)}
            >
              {builder.catalog.step_types.map((item) => (
                <option key={item.type} value={item.type}>
                  {item.label}
                </option>
              ))}
            </select>
            <button type="button" className="workflows-button" data-variant="outline" onClick={add}>
              {strings.add_step ?? "Add a step"}
            </button>
          </>
        ) : null}
        <span className="workflows-toolbar-spacer" />
        <button
          type="button"
          className="workflows-button"
          data-variant="ghost"
          aria-pressed={view === "list"}
          onClick={() => setView(view === "list" ? "canvas" : "list")}
        >
          {strings.list_view ?? "List"}
        </button>
        <button
          type="button"
          className="workflows-button"
          data-variant="ghost"
          aria-label={strings.zoom_out}
          onClick={() => setZoom((value) => Math.max(0.4, Math.round((value - 0.1) * 10) / 10))}
        >
          −
        </button>
        <span className="workflows-zoom" aria-live="off">
          {Math.round(zoom * 100)}%
        </span>
        <button
          type="button"
          className="workflows-button"
          data-variant="ghost"
          aria-label={strings.zoom_in}
          onClick={() => setZoom((value) => Math.min(2, Math.round((value + 0.1) * 10) / 10))}
        >
          +
        </button>
        {editable ? (
          <button
            type="button"
            className="workflows-button"
            data-variant="primary"
            disabled={!dirty || saving}
            onClick={() => void save()}
            data-save=""
          >
            {saving ? (strings.saving ?? "Saving…") : (strings.save_draft ?? "Save draft")}
          </button>
        ) : null}
        <span className="workflows-status" role="status">
          {message ?? (dirty ? strings.unsaved : "")}
        </span>
      </div>

      {problemKeys.length > 0 || problems.unreachable.length > 0 ? (
        <div className="workflows-problems" data-problems="">
          {problemKeys.length > 0 ? (
            <>
              <p className="workflows-label">{strings.problems}</p>
              <ul>
                {problemKeys.map((key) => {
                  const [kind, item] = key.split(".", 2) as [string, string | undefined]
                  const target: Selection = item ? { kind: kind === "steps" ? "step" : "transition", key: item } : null
                  return (problems.errors[key] ?? []).map((error) => (
                    <li key={`${key}${error}`}>
                      {target ? (
                        <button type="button" className="workflows-link" onClick={() => select(target)}>
                          {error}
                        </button>
                      ) : (
                        error
                      )}
                    </li>
                  ))
                })}
              </ul>
            </>
          ) : null}
          {problems.unreachable.length > 0 ? (
            <p>
              {strings.unreachable}: {problems.unreachable.map((key) => label({ kind: "step", key })).join(", ")}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="workflows-main">
        {view === "canvas" ? (
          definition.steps.length === 0 ? (
            <p className="workflows-empty">{strings.empty_canvas}</p>
          ) : (
            <Canvas
              definition={definition}
              catalog={builder.catalog}
              strings={strings}
              locale={locale}
              selection={selection}
              readOnly={!editable}
              problems={problemKeys}
              zoom={zoom}
              onSelect={select}
              onMove={move}
              onConnect={connect}
              onDelete={remove}
            />
          )
        ) : (
          <ListView definition={definition} strings={strings} locale={locale} onSelect={select} selection={selection} />
        )}
        <Inspector
          definition={definition}
          catalog={builder.catalog}
          strings={strings}
          selection={selection}
          problems={problems.errors}
          readOnly={!editable}
          onChangeStep={(key, changes: Partial<StepDef>) => change(updateStep(definition, key, changes))}
          onChangeTransition={(key, changes: Partial<TransitionDef>) =>
            change(updateTransition(definition, key, changes))
          }
          onConnect={connect}
          onDelete={remove}
          onSelect={select}
        />
      </div>

      <div className="workflows-sr-only" aria-live="polite" data-announcer="">
        {announcement}
      </div>
    </div>
  )
}

/** The steps and their transitions as a list, an alternative to the diagram */
export function ListView({
  definition,
  strings,
  locale,
  selection,
  onSelect,
}: {
  definition: Definition
  strings: Strings
  locale?: string
  selection?: Selection
  onSelect?: (selection: Selection) => void
}): React.JSX.Element {
  return (
    <ol className="workflows-list-view" aria-label={strings.steps}>
      {definition.steps.map((step) => (
        <li key={step.key} data-list-step={step.key}>
          <button
            type="button"
            className="workflows-link"
            aria-pressed={selection?.kind === "step" && selection.key === step.key}
            onClick={() => onSelect?.({ kind: "step", key: step.key })}
            dir="auto"
          >
            {stepName(step, locale)}
          </button>
          <ul>
            {definition.transitions
              .filter((transition) => transition.from === step.key)
              .map((transition) => (
                <li key={transition.key}>
                  <button
                    type="button"
                    className="workflows-link"
                    aria-pressed={selection?.kind === "transition" && selection.key === transition.key}
                    onClick={() => onSelect?.({ kind: "transition", key: transition.key })}
                    dir="auto"
                  >
                    {resolveText(transition.label, locale) || transition.key} →{" "}
                    {stepName(getStep(definition, transition.to) ?? { key: transition.to, type: "task" }, locale)}
                  </button>
                </li>
              ))}
          </ul>
        </li>
      ))}
    </ol>
  )
}
