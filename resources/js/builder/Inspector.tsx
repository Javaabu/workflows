import * as React from "react"
import type {
  ActionCallDef,
  ActionField,
  AssigneeDef,
  Catalog,
  ConditionRule,
  Definition,
  StepDef,
  Strings,
  TransitionDef,
} from "../types"
import { getStep, resolveText, stepName } from "./definition"
import { Checkbox, Field, KeyValueField, LocalizedField, SelectField, TextField, parseValue } from "./fields"
import type { Selection } from "./Canvas"

export interface InspectorProps {
  definition: Definition
  catalog: Catalog
  strings: Strings
  selection: Selection
  problems: Record<string, string[]>
  readOnly?: boolean
  onChangeStep: (key: string, changes: Partial<StepDef>) => void
  onChangeTransition: (key: string, changes: Partial<TransitionDef>) => void
  onConnect: (from: string, to: string) => void
  onDelete: (selection: NonNullable<Selection>) => void
  onSelect: (selection: Selection) => void
}

/** The panel that edits the selected step or transition */
export function Inspector(props: InspectorProps): React.JSX.Element {
  const { definition, selection, strings, problems } = props

  if (!selection) {
    return (
      <aside className="workflows-inspector" aria-label={strings.step ?? "Step"}>
        <p className="workflows-help">{strings.no_selection}</p>
      </aside>
    )
  }

  const errors = problems[`${selection.kind === "step" ? "steps" : "transitions"}.${selection.key}`] ?? []

  return (
    <aside
      className="workflows-inspector"
      aria-label={selection.kind === "step" ? strings.step : strings.transition}
      data-inspector={selection.kind}
    >
      {errors.length > 0 ? (
        <ul className="workflows-problems" role="alert">
          {errors.map((error) => (
            <li key={error}>{error}</li>
          ))}
        </ul>
      ) : null}
      {selection.kind === "step"
        ? (() => {
            const step = getStep(definition, selection.key)
            return step ? <StepInspector key={step.key} {...props} step={step} /> : null
          })()
        : (() => {
            const transition = definition.transitions.find((item) => item.key === selection.key)
            return transition ? <TransitionInspector key={transition.key} {...props} transition={transition} /> : null
          })()}
    </aside>
  )
}

function StepInspector({
  step,
  definition,
  catalog,
  strings,
  readOnly,
  onChangeStep,
  onConnect,
  onDelete,
  onSelect,
}: InspectorProps & { step: StepDef }): React.JSX.Element {
  const change = (changes: Partial<StepDef>) => onChangeStep(step.key, changes)
  const [target, setTarget] = React.useState("")
  const [key, setKey] = React.useState(step.key)

  const out = definition.transitions.filter((transition) => transition.from === step.key)
  const targets = definition.steps.filter(
    (other) => other.type !== "start" && (other.key !== step.key || step.type === "task"),
  )
  const form = catalog.forms.find((item) => item.key === step.form)

  return (
    <div className="workflows-stack">
      <h2 className="workflows-heading" dir="auto">
        {stepName(step)}
      </h2>
      <LocalizedField
        label={strings.step_name ?? "Name"}
        value={step.name}
        locales={catalog.locales}
        disabled={readOnly}
        template={strings.localized_in}
        onChange={(name) => change({ name })}
      />
      <Field label={strings.key ?? "Key"}>
        {(id) => (
          <input
            id={id}
            className="workflows-input"
            value={key}
            dir="ltr"
            disabled={readOnly}
            onChange={(event) => setKey(event.target.value)}
            onBlur={() => {
              if (key !== step.key && /^[a-z][a-z0-9_]*$/.test(key) && !getStep(definition, key)) {
                change({ key })
                onSelect({ kind: "step", key })
              } else setKey(step.key)
            }}
          />
        )}
      </Field>
      <SelectField
        label={strings.type ?? "Type"}
        value={step.type}
        disabled={readOnly}
        options={catalog.step_types.map((type) => ({ value: type.type, label: type.label }))}
        help={catalog.step_types.find((type) => type.type === step.type)?.description}
        onChange={(type) => change({ type: type as StepDef["type"] })}
      />
      <LocalizedField
        label={strings.step_description ?? "Description"}
        value={step.description}
        locales={catalog.locales}
        disabled={readOnly}
        multiline
        template={strings.localized_in}
        onChange={(description) => change({ description })}
      />

      {step.type === "task" ? (
        <>
          <AssigneesEditor
            label={strings.assignees ?? "Assigned to"}
            value={step.assignees ?? []}
            catalog={catalog}
            definition={definition}
            strings={strings}
            disabled={readOnly}
            onChange={(assignees) => change({ assignees })}
          />
          <TextField
            label={strings.due_in ?? "Due in (hours)"}
            type="number"
            value={step.due_in}
            disabled={readOnly}
            onChange={(value) => change({ due_in: value === "" ? undefined : Number(value) })}
          />
        </>
      ) : null}

      {step.type === "task" || step.type === "start" ? (
        <>
          <SelectField
            label={strings.form ?? "Form"}
            help={strings.form_help}
            value={step.form}
            placeholder={strings.no_form}
            disabled={readOnly}
            options={catalog.forms.map((item) => ({ value: item.key, label: item.name }))}
            onChange={(value) => change({ form: value || undefined, form_version: undefined })}
          />
          {form ? (
            <SelectField
              label={strings.form_version ?? "Version"}
              value={step.form_version ? String(step.form_version) : ""}
              placeholder={strings.latest_version}
              disabled={readOnly}
              options={form.versions.map((version) => ({ value: String(version), label: String(version) }))}
              onChange={(value) => change({ form_version: value ? Number(value) : undefined })}
            />
          ) : null}
        </>
      ) : null}

      {step.type === "end" ? (
        <>
          <TextField
            label={strings.outcome ?? "Outcome"}
            value={step.outcome}
            dir="ltr"
            disabled={readOnly}
            onChange={(outcome) => change({ outcome })}
          />
        </>
      ) : null}

      <SelectField
        label={strings.color ?? "Colour"}
        value={step.color}
        placeholder={strings.none}
        disabled={readOnly}
        options={catalog.colors}
        onChange={(color) => change({ color: color || undefined })}
      />

      <ActionsEditor
        label={strings.step_actions ?? "When the step starts"}
        value={step.actions ?? []}
        catalog={catalog}
        definition={definition}
        strings={strings}
        disabled={readOnly}
        onChange={(actions) => change({ actions })}
      />

      {step.type === "end" ? null : (
        <fieldset className="workflows-fieldset">
          <legend className="workflows-label">{strings.transitions ?? "Transitions"}</legend>
          <ul className="workflows-list">
            {out.map((transition) => (
              <li key={transition.key}>
                <button
                  type="button"
                  className="workflows-link"
                  onClick={() => onSelect({ kind: "transition", key: transition.key })}
                  dir="auto"
                >
                  {resolveText(transition.label) || transition.key} →{" "}
                  {stepName(getStep(definition, transition.to) ?? { key: transition.to, type: "task" })}
                </button>
              </li>
            ))}
          </ul>
          {readOnly ? null : (
            <div className="workflows-row">
              <SelectField
                label={strings.transition_to ?? "Go to"}
                value={target}
                placeholder="—"
                options={targets.map((other) => ({ value: other.key, label: stepName(other) }))}
                onChange={setTarget}
              />
              <button
                type="button"
                className="workflows-button"
                data-variant="outline"
                disabled={target === ""}
                onClick={() => {
                  onConnect(step.key, target)
                  setTarget("")
                }}
              >
                {strings.add_transition ?? "Add a transition"}
              </button>
            </div>
          )}
          <p className="workflows-help">{strings.connect_help}</p>
        </fieldset>
      )}

      {readOnly ? null : (
        <button
          type="button"
          className="workflows-button"
          data-variant="danger"
          onClick={() => onDelete({ kind: "step", key: step.key })}
        >
          {strings.delete ?? "Delete"}
        </button>
      )}
    </div>
  )
}

function TransitionInspector({
  transition,
  definition,
  catalog,
  strings,
  readOnly,
  onChangeTransition,
  onDelete,
  onSelect,
}: InspectorProps & { transition: TransitionDef }): React.JSX.Element {
  const change = (changes: Partial<TransitionDef>) => onChangeTransition(transition.key, changes)
  const from = getStep(definition, transition.from)
  const human = from?.type === "task"
  const steps = definition.steps.map((step) => ({ value: step.key, label: stepName(step) }))

  return (
    <div className="workflows-stack">
      <h2 className="workflows-heading" dir="auto">
        {resolveText(transition.label) || transition.key}
      </h2>
      <p className="workflows-help">
        <button
          type="button"
          className="workflows-link"
          onClick={() => onSelect({ kind: "step", key: transition.from })}
        >
          {from ? stepName(from) : transition.from}
        </button>{" "}
        →{" "}
        <button type="button" className="workflows-link" onClick={() => onSelect({ kind: "step", key: transition.to })}>
          {stepName(getStep(definition, transition.to) ?? { key: transition.to, type: "task" })}
        </button>
      </p>
      <SelectField
        label={strings.from ?? "From"}
        value={transition.from}
        options={steps}
        disabled={readOnly}
        onChange={(value) => change({ from: value })}
      />
      <SelectField
        label={strings.to ?? "To"}
        value={transition.to}
        options={steps}
        disabled={readOnly}
        onChange={(value) => change({ to: value })}
      />
      {human ? (
        <>
          <LocalizedField
            label={strings.label ?? "Label"}
            value={transition.label}
            locales={catalog.locales}
            disabled={readOnly}
            template={strings.localized_in}
            onChange={(label) => change({ label })}
          />
          <SelectField
            label={strings.color ?? "Colour"}
            value={transition.color}
            placeholder={strings.none}
            options={catalog.colors}
            disabled={readOnly}
            onChange={(color) => change({ color: color || undefined })}
          />
          <SelectField
            label={strings.comment ?? "Comment"}
            value={transition.comment ?? "optional"}
            options={catalog.comments}
            disabled={readOnly}
            onChange={(comment) =>
              change({ comment: comment === "optional" ? undefined : (comment as TransitionDef["comment"]) })
            }
          />
          {from?.form ? (
            <Checkbox
              label={strings.skip_form ?? "Skip the step's form"}
              checked={transition.skip_form ?? false}
              disabled={readOnly}
              onChange={(skip_form) => change({ skip_form: skip_form || undefined })}
            />
          ) : null}
        </>
      ) : null}

      <ConditionsEditor
        value={transition.conditions?.rules ?? []}
        match={transition.conditions?.match ?? "all"}
        catalog={catalog}
        strings={strings}
        disabled={readOnly}
        onChange={(rules, match) => change({ conditions: rules.length === 0 ? undefined : { match, rules } })}
      />

      <ActionsEditor
        label={strings.transition_actions ?? "When it's taken"}
        value={transition.actions ?? []}
        catalog={catalog}
        definition={definition}
        strings={strings}
        disabled={readOnly}
        onChange={(actions) => change({ actions })}
      />

      {readOnly ? null : (
        <button
          type="button"
          className="workflows-button"
          data-variant="danger"
          onClick={() => onDelete({ kind: "transition", key: transition.key })}
        >
          {strings.delete ?? "Delete"}
        </button>
      )}
    </div>
  )
}

export function AssigneesEditor({
  label,
  value,
  catalog,
  definition,
  strings,
  disabled,
  extra,
  onChange,
}: {
  label: string
  value: AssigneeDef[]
  catalog: Catalog
  definition: Definition
  strings: Strings
  disabled?: boolean
  /** Types only some editors offer, e.g. the notify action's "assignees" */
  extra?: Record<string, string>
  onChange: (value: AssigneeDef[]) => void
}): React.JSX.Element {
  const types = catalog.assignee_types.filter((type) => !type.notify_only || (extra && type.type in extra))

  const valueInput = (assignee: AssigneeDef, index: number) => {
    const type = types.find((item) => item.type === assignee.type)
    if (!type?.needs_value) return null
    const set = (next: string) =>
      onChange(value.map((item, i) => (i === index ? { ...item, value: next || undefined } : item)))
    const options =
      assignee.type === "role"
        ? catalog.roles
        : assignee.type === "permission"
          ? catalog.permissions
          : assignee.type === "step"
            ? definition.steps.map((step) => step.key)
            : null

    if (options && options.length > 0) {
      return (
        <SelectField
          label={strings.value ?? "Value"}
          value={assignee.value}
          placeholder="—"
          disabled={disabled}
          options={options.map((option) => ({
            value: option,
            label: assignee.type === "step" ? stepName(getStep(definition, option)!) : option,
          }))}
          onChange={set}
        />
      )
    }

    return (
      <TextField
        label={strings.value ?? "Value"}
        help={type.help}
        value={assignee.value}
        dir="ltr"
        disabled={disabled}
        onChange={set}
      />
    )
  }

  return (
    <fieldset className="workflows-fieldset" data-assignees="">
      <legend className="workflows-label">{label}</legend>
      {value.map((assignee, index) => (
        <div key={index} className="workflows-card">
          <SelectField
            label={strings.type ?? "Type"}
            value={assignee.type}
            disabled={disabled}
            options={types.map((type) => ({ value: type.type, label: type.label }))}
            onChange={(type) => onChange(value.map((item, i) => (i === index ? { type } : item)))}
          />
          {valueInput(assignee, index)}
          {disabled ? null : (
            <button
              type="button"
              className="workflows-button"
              data-variant="ghost"
              onClick={() => onChange(value.filter((_, i) => i !== index))}
            >
              {strings.remove ?? "Remove"}
            </button>
          )}
        </div>
      ))}
      {disabled ? null : (
        <button
          type="button"
          className="workflows-button"
          data-variant="outline"
          onClick={() => onChange([...value, { type: types[0]?.type ?? "starter" }])}
        >
          {strings.add_assignee ?? "Add an assignee"}
        </button>
      )}
    </fieldset>
  )
}

export function ConditionsEditor({
  value,
  match,
  catalog,
  strings,
  disabled,
  onChange,
}: {
  value: ConditionRule[]
  match: "all" | "any"
  catalog: Catalog
  strings: Strings
  disabled?: boolean
  onChange: (rules: ConditionRule[], match: "all" | "any") => void
}): React.JSX.Element {
  const set = (index: number, changes: Partial<ConditionRule>) =>
    onChange(
      value.map((rule, i) => (i === index ? { ...rule, ...changes } : rule)),
      match,
    )

  return (
    <fieldset className="workflows-fieldset" data-conditions="">
      <legend className="workflows-label">{strings.conditions ?? "Conditions"}</legend>
      <p className="workflows-help">{strings.conditions_help}</p>
      {value.length > 1 ? (
        <SelectField
          label={strings.conditions ?? "Conditions"}
          value={match}
          disabled={disabled}
          options={[
            { value: "all", label: strings.match_all ?? "All of them" },
            { value: "any", label: strings.match_any ?? "Any of them" },
          ]}
          onChange={(next) => onChange(value, next === "any" ? "any" : "all")}
        />
      ) : null}
      {value.map((rule, index) => {
        const operator = catalog.operators.find((item) => item.value === rule.operator)
        return (
          <div key={index} className="workflows-card" data-condition={index}>
            <TextField
              label={strings.field ?? "Field"}
              value={rule.field}
              dir="ltr"
              disabled={disabled}
              onChange={(field) => set(index, { field })}
            />
            <SelectField
              label={strings.operator ?? "Operator"}
              value={rule.operator}
              disabled={disabled}
              options={catalog.operators.map((item) => ({ value: item.value, label: item.label }))}
              onChange={(next) =>
                set(index, {
                  operator: next,
                  value: catalog.operators.find((item) => item.value === next)?.unary ? undefined : rule.value,
                })
              }
            />
            {operator?.unary ? null : (
              <TextField
                label={strings.value ?? "Value"}
                help={operator?.list ? strings.values_help : undefined}
                value={
                  Array.isArray(rule.value)
                    ? rule.value.join(", ")
                    : rule.value === undefined || rule.value === null
                      ? ""
                      : String(rule.value)
                }
                disabled={disabled}
                onChange={(text) =>
                  set(index, {
                    value: operator?.list
                      ? text
                          .split(",")
                          .map((item) => parseValue(item.trim()))
                          .filter((item) => item !== "")
                      : parseValue(text),
                  })
                }
              />
            )}
            {disabled ? null : (
              <button
                type="button"
                className="workflows-button"
                data-variant="ghost"
                onClick={() =>
                  onChange(
                    value.filter((_, i) => i !== index),
                    match,
                  )
                }
              >
                {strings.remove ?? "Remove"}
              </button>
            )}
          </div>
        )
      })}
      {disabled ? null : (
        <button
          type="button"
          className="workflows-button"
          data-variant="outline"
          onClick={() => onChange([...value, { field: "", operator: "=", value: "" }], match)}
        >
          {strings.add_condition ?? "Add a condition"}
        </button>
      )}
    </fieldset>
  )
}

export function ActionsEditor({
  label,
  value,
  catalog,
  definition,
  strings,
  disabled,
  onChange,
}: {
  label: string
  value: ActionCallDef[]
  catalog: Catalog
  definition: Definition
  strings: Strings
  disabled?: boolean
  onChange: (value: ActionCallDef[]) => void
}): React.JSX.Element {
  const [adding, setAdding] = React.useState("")
  const setSettings = (index: number, settings: Record<string, unknown>) =>
    onChange(value.map((call, i) => (i === index ? { ...call, settings } : call)))

  return (
    <fieldset className="workflows-fieldset" data-actions="">
      <legend className="workflows-label">{label}</legend>
      {value.map((call, index) => {
        const action = catalog.actions.find((item) => item.key === call.action)
        return (
          <div key={index} className="workflows-card" data-action={call.action}>
            <p className="workflows-card-title">{action?.name ?? call.action}</p>
            {action?.description ? <p className="workflows-help">{action.description}</p> : null}
            {Object.entries(action?.fields ?? {}).map(([name, field]) => (
              <ActionSetting
                key={name}
                name={name}
                field={field}
                value={call.settings[name]}
                catalog={catalog}
                definition={definition}
                strings={strings}
                disabled={disabled}
                onChange={(next) => setSettings(index, cleanSettings({ ...call.settings, [name]: next }))}
              />
            ))}
            {disabled ? null : (
              <button
                type="button"
                className="workflows-button"
                data-variant="ghost"
                onClick={() => onChange(value.filter((_, i) => i !== index))}
              >
                {strings.remove ?? "Remove"}
              </button>
            )}
          </div>
        )
      })}
      {disabled ? null : (
        <div className="workflows-row">
          <SelectField
            label={strings.add_action ?? "Add an action"}
            value={adding}
            placeholder="—"
            options={catalog.actions.map((action) => ({ value: action.key, label: action.name }))}
            onChange={setAdding}
          />
          <button
            type="button"
            className="workflows-button"
            data-variant="outline"
            disabled={adding === ""}
            onClick={() => {
              onChange([...value, { action: adding, settings: {} }])
              setAdding("")
            }}
          >
            {strings.add_row ?? "Add"}
          </button>
        </div>
      )}
    </fieldset>
  )
}

function cleanSettings(settings: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(settings).filter(
      ([, item]) => item !== undefined && item !== "" && !(Array.isArray(item) && item.length === 0),
    ),
  )
}

function ActionSetting({
  name,
  field,
  value,
  catalog,
  definition,
  strings,
  disabled,
  onChange,
}: {
  name: string
  field: ActionField
  value: unknown
  catalog: Catalog
  definition: Definition
  strings: Strings
  disabled?: boolean
  onChange: (value: unknown) => void
}): React.JSX.Element {
  const label = field.required ? `${field.label} *` : field.label

  switch (field.type) {
    case "textarea":
    case "text":
      return field.localized ? (
        <LocalizedField
          label={label}
          value={value as Record<string, string> | string | undefined}
          locales={catalog.locales}
          disabled={disabled}
          multiline={field.type === "textarea"}
          template={strings.localized_in}
          onChange={onChange}
        />
      ) : (
        <TextField
          label={label}
          help={field.help}
          value={typeof value === "string" ? value : ""}
          disabled={disabled}
          dir="auto"
          onChange={onChange}
        />
      )
    case "select":
      return (
        <SelectField
          label={label}
          help={field.help}
          value={typeof value === "string" ? value : ""}
          placeholder="—"
          disabled={disabled}
          options={Object.entries(field.options ?? {}).map(([key, text]) => ({ value: key, label: text }))}
          onChange={(next) => onChange(next || undefined)}
        />
      )
    case "checkboxes": {
      const selected = Array.isArray(value) ? (value as string[]) : []
      return (
        <fieldset className="workflows-fieldset" data-setting={name}>
          <legend className="workflows-label">{label}</legend>
          {Object.entries(field.options ?? {}).map(([key, text]) => (
            <Checkbox
              key={key}
              label={text}
              checked={selected.includes(key)}
              disabled={disabled}
              onChange={(checked) => onChange(checked ? [...selected, key] : selected.filter((item) => item !== key))}
            />
          ))}
        </fieldset>
      )
    }
    case "key_value":
      return (
        <KeyValueField
          label={label}
          help={field.help}
          value={value as Record<string, unknown> | undefined}
          disabled={disabled}
          strings={strings}
          onChange={onChange}
        />
      )
    case "assignees":
      return (
        <AssigneesEditor
          label={label}
          value={Array.isArray(value) ? (value as AssigneeDef[]) : []}
          catalog={catalog}
          definition={definition}
          strings={strings}
          disabled={disabled}
          extra={field.extra}
          onChange={onChange}
        />
      )
    default:
      return (
        <TextField
          label={label}
          value={typeof value === "string" ? value : ""}
          disabled={disabled}
          onChange={onChange}
        />
      )
  }
}
