import * as React from "react"
import type { Catalog, Text } from "../types"
import { resolveText, setText } from "./definition"

interface FieldProps {
  label: string
  help?: string | null
  children: (id: string, describedBy?: string) => React.ReactNode
}

/** A label, the control and its help text */
export function Field({ label, help, children }: FieldProps): React.JSX.Element {
  const id = React.useId()
  return (
    <div className="workflows-field">
      <label htmlFor={id} className="workflows-label">
        {label}
      </label>
      {children(id, help ? `${id}-help` : undefined)}
      {help ? (
        <p id={`${id}-help`} className="workflows-help">
          {help}
        </p>
      ) : null}
    </div>
  )
}

export function TextField({
  label,
  help,
  value,
  onChange,
  disabled,
  dir = "auto",
  type = "text",
}: {
  label: string
  help?: string | null
  value: string | number | undefined
  onChange: (value: string) => void
  disabled?: boolean
  dir?: string
  type?: string
}): React.JSX.Element {
  return (
    <Field label={label} help={help}>
      {(id, describedBy) => (
        <input
          id={id}
          type={type}
          className="workflows-input"
          value={value ?? ""}
          aria-describedby={describedBy}
          disabled={disabled}
          dir={dir}
          onChange={(event) => onChange(event.target.value)}
        />
      )}
    </Field>
  )
}

export function SelectField({
  label,
  help,
  value,
  options,
  onChange,
  disabled,
  placeholder,
}: {
  label: string
  help?: string | null
  value: string | undefined
  options: { value: string; label: string }[]
  onChange: (value: string) => void
  disabled?: boolean
  placeholder?: string
}): React.JSX.Element {
  return (
    <Field label={label} help={help}>
      {(id, describedBy) => (
        <select
          id={id}
          className="workflows-input"
          value={value ?? ""}
          aria-describedby={describedBy}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
        >
          {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      )}
    </Field>
  )
}

/** Text in every locale: one input per active locale (Thaana and right to left for Dhivehi) */
export function LocalizedField({
  label,
  value,
  onChange,
  locales,
  disabled,
  multiline,
  template,
}: {
  label: string
  value: Text
  onChange: (value: Text) => void
  locales: Catalog["locales"]
  disabled?: boolean
  multiline?: boolean
  template?: string
}): React.JSX.Element {
  const codes = locales.map((locale) => locale.code)
  const list = locales.length > 0 ? locales : [{ code: "en", name: "", rtl: false }]

  return (
    <>
      {list.map((locale) => {
        const text =
          typeof value === "object" && value ? (value[locale.code] ?? "") : locale === list[0] ? resolveText(value) : ""
        const inputLabel =
          list.length > 1
            ? (template ?? ":label (:language)").replace(":label", label).replace(":language", locale.name)
            : label
        return (
          <Field key={locale.code} label={inputLabel}>
            {(id) => {
              const props = {
                id,
                className: "workflows-input",
                value: text,
                disabled,
                lang: list.length > 1 ? locale.code : undefined,
                dir: locale.rtl ? "rtl" : "auto",
                "data-thaana": locale.code === "dv" ? "" : undefined,
                onChange: (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
                  onChange(setText(value, event.target.value, locale.code, codes.length > 0 ? codes : [locale.code])),
              }
              return multiline ? <textarea rows={3} {...props} /> : <input type="text" {...props} />
            }}
          </Field>
        )
      })}
    </>
  )
}

export function Checkbox({
  label,
  checked,
  onChange,
  disabled,
}: {
  label: string
  checked: boolean
  onChange: (checked: boolean) => void
  disabled?: boolean
}): React.JSX.Element {
  return (
    <label className="workflows-checkbox">
      <input
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
      />
      {label}
    </label>
  )
}

/** Rows of name and value, for headers, payloads and attributes */
export function KeyValueField({
  label,
  help,
  value,
  onChange,
  disabled,
  strings,
}: {
  label: string
  help?: string
  value: Record<string, unknown> | undefined
  onChange: (value: Record<string, unknown>) => void
  disabled?: boolean
  strings: Record<string, string>
}): React.JSX.Element {
  const [rows, setRows] = React.useState(() =>
    Object.entries(value ?? {}).map(([key, item]) => ({
      key,
      value: typeof item === "string" ? item : JSON.stringify(item),
    })),
  )

  const update = (next: { key: string; value: string }[]) => {
    setRows(next)
    onChange(Object.fromEntries(next.filter((row) => row.key !== "").map((row) => [row.key, parseValue(row.value)])))
  }

  return (
    <fieldset className="workflows-fieldset">
      <legend className="workflows-label">{label}</legend>
      {help ? <p className="workflows-help">{help}</p> : null}
      {rows.map((row, index) => (
        <div key={index} className="workflows-row">
          <input
            className="workflows-input"
            aria-label={strings.settings_key ?? "Name"}
            value={row.key}
            disabled={disabled}
            dir="ltr"
            onChange={(event) =>
              update(rows.map((item, i) => (i === index ? { ...item, key: event.target.value } : item)))
            }
          />
          <input
            className="workflows-input"
            aria-label={strings.settings_value ?? "Value"}
            value={row.value}
            disabled={disabled}
            dir="auto"
            onChange={(event) =>
              update(rows.map((item, i) => (i === index ? { ...item, value: event.target.value } : item)))
            }
          />
          {disabled ? null : (
            <button
              type="button"
              className="workflows-button"
              data-variant="ghost"
              onClick={() => update(rows.filter((_, i) => i !== index))}
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
          onClick={() => update([...rows, { key: "", value: "" }])}
        >
          {strings.add_row ?? "Add"}
        </button>
      )}
    </fieldset>
  )
}

/** Numbers, true, false and null typed as JSON; everything else is text */
export function parseValue(value: string): unknown {
  const trimmed = value.trim()
  if (/^-?\d+(\.\d+)?$/.test(trimmed)) return Number(trimmed)
  if (trimmed === "true") return true
  if (trimmed === "false") return false
  if (trimmed === "null") return null
  return value
}
