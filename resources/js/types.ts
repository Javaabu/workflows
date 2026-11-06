import type { StencilSchema } from "@javaabu/stencil"

export type Strings = Record<string, string>

/** Text from a definition: a string, or a map of locale => text */
export type Text = string | Record<string, string> | null | undefined

export type StepType = "start" | "task" | "decision" | "action" | "end"

export interface Position {
  x: number
  y: number
}

export interface AssigneeDef {
  type: string
  value?: string
}

export interface ConditionRule {
  field: string
  operator: string
  value?: unknown
}

export interface ConditionsDef {
  match: "all" | "any"
  rules: ConditionRule[]
}

export interface ActionCallDef {
  action: string
  settings: Record<string, unknown>
}

export interface StepDef {
  key: string
  type: StepType
  name?: Text
  description?: Text
  assignees?: AssigneeDef[]
  form?: string
  form_version?: number
  actions?: ActionCallDef[]
  outcome?: string
  color?: string
  due_in?: number
  position?: Position
}

export interface TransitionDef {
  key: string
  from: string
  to: string
  label?: Text
  color?: string
  conditions?: ConditionsDef
  comment?: "none" | "optional" | "required"
  skip_form?: boolean
  actions?: ActionCallDef[]
}

/** A version's structure: what the builder edits and saves */
export interface Definition {
  workflows: number
  steps: StepDef[]
  transitions: TransitionDef[]
}

export interface ActionField {
  type: "text" | "textarea" | "select" | "checkboxes" | "key_value" | "assignees"
  label: string
  help?: string
  required?: boolean
  localized?: boolean
  options?: Record<string, string>
  extra?: Record<string, string>
}

export interface Catalog {
  step_types: { type: StepType; label: string; description: string | null; automatic: boolean }[]
  actions: {
    key: string
    name: string
    description: string | null
    icon: string
    fields: Record<string, ActionField>
  }[]
  assignee_types: { type: string; label: string; needs_value: boolean; help: string | null; notify_only?: boolean }[]
  roles: string[]
  permissions: string[]
  forms: { key: string; name: string; versions: number[] }[]
  operators: { value: string; label: string; unary: boolean; list: boolean }[]
  colors: { value: string; label: string }[]
  comments: { value: string; label: string }[]
  locales: { code: string; name: string; rtl: boolean }[]
}

export type Problems = Record<string, string[]>

export interface BuilderData {
  definition: Definition
  errors: Problems
  unreachable: string[]
  catalog: Catalog
  editable: boolean
}

export interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type WorkflowStatus = "draft" | "published" | "changed"

/** A workflow as the pages list it (BuilderData::workflow()) */
export interface WorkflowData {
  id: number
  key: string
  name: string
  description: string | null
  subject_type: string | null
  subject_label: string | null
  status: WorkflowStatus
  status_label: string
  is_locked: boolean
  is_active: boolean
  published_version: number | null
  draft_version: number | null
  has_draft: boolean
  instances_count: number
  active_count: number
  updated_at: string | null
  urls: Partial<Record<"show" | "edit" | "test" | "instances" | "versions" | "export" | "destroy", string>>
}

export interface VersionData {
  id: number
  number: number
  is_draft: boolean
  is_current: boolean
  published_at: string | null
  published_at_label: string | null
  author: string | null
  instances_count: number
  steps_count: number
}

export interface InstanceData {
  id: number
  title: string
  workflow: string | null
  workflow_key: string | null
  version: number | null
  status: "active" | "completed" | "cancelled"
  status_label: string
  outcome: string | null
  outcome_label: string | null
  step: string | null
  step_name: string | null
  step_color: string | null
  subject: { type: string; id: string | number; label: string } | null
  starter: string | null
  assignees: string[]
  due_at: string | null
  due_at_label: string | null
  overdue: boolean
  started_at: string | null
  started_at_label: string | null
  completed_at_label: string | null
  url: string | null
}

export interface HistoryEntry {
  id: number
  event: string
  label: string
  step: string | null
  transition: string | null
  color: string | null
  actor: string | null
  comment: string | null
  error: string | null
  at: string | null
  at_label: string | null
}

export interface TransitionButton {
  key: string
  label: string
  color: string
  comment: "none" | "optional" | "required"
  skips_form: boolean
}

export interface SimulationStep {
  step: string
  type: StepType
  name: string
  actions: { action: string; description: string }[]
  transition?: string
  transition_label?: string | null
  reason?: "conditions" | "default" | "chosen"
  transition_actions?: { action: string; description: string }[]
  options?: { key: string; label: string | null; to: string; available: boolean }[]
}

export interface SimulationData {
  status: "completed" | "waiting" | "stuck" | "looped"
  outcome: string | null
  message: string | null
  path: SimulationStep[]
  options: { key: string; label: string | null; to: string; available: boolean }[]
  data: Record<string, unknown>
}

interface PageProps {
  status?: string | null
  strings: Strings
}

/** workflows::Workflows/Index */
export interface WorkflowsIndexProps extends PageProps {
  workflows: WorkflowData[]
  pagination: Pagination
  search: string
  can: { create: boolean }
  urls: { index: string; create?: string; assistant?: string }
}

/** workflows::Workflows/Edit: a new workflow, or a workflow's settings */
export interface WorkflowsEditProps extends PageProps {
  form: StencilSchema
  record: WorkflowData | null
  title: string
  back_url: string
}

export interface BuilderUrls {
  index?: string
  edit?: string
  save?: string
  publish?: string
  discard?: string
  test?: string
  versions?: string
  instances?: string
  export?: string
  destroy?: string
  assistant?: string
}

export interface BuilderCan {
  update: boolean
  publish: boolean
  delete: boolean
  view_instances: boolean
}

/** workflows::Workflows/Show: the builder */
export interface WorkflowsShowProps extends PageProps {
  workflow: WorkflowData
  builder: BuilderData
  can: BuilderCan
  urls: BuilderUrls
}

/** workflows::Workflows/Versions */
export interface WorkflowsVersionsProps extends PageProps {
  record: WorkflowData
  versions: VersionData[]
  back_url: string
}

/** workflows::Workflows/Test: the tester */
export interface WorkflowsTestProps extends PageProps {
  workflow: WorkflowData
  definition: Definition | null
  catalog: Catalog
  tasks: { key: string; name: string; transitions: { key: string; label: string }[] }[]
  input: { data: string; subject: string; choices: Record<string, string>; draft: boolean }
  simulation: SimulationData | null
  has_draft: boolean
  urls: { run: string; back: string }
}

/** workflows::Instances/Index */
export interface InstancesIndexProps extends PageProps {
  record: WorkflowData
  instances: InstanceData[]
  pagination: Pagination
  filter: string | null
  statuses: { value: string; label: string }[]
  urls: { index: string; back: string }
}

/** workflows::Instances/Show */
export interface InstancesShowProps extends PageProps {
  instance: InstanceData
  workflow: { key: string | null; name: string | null }
  history: HistoryEntry[]
  entries: { step: string; name: string; display: StencilSchema }[]
  data: { key: string; value: string }[]
  transitions: TransitionButton[]
  form: StencilSchema | null
  can: { act: boolean; cancel: boolean }
  urls: { transition: string; cancel: string; back: string }
}

/** workflows::Tasks/Index */
export interface TasksIndexProps extends PageProps {
  items: InstanceData[]
  view: "assigned" | "started"
  pagination: Pagination
  startable: { key: string; name: string; description: string | null; url: string }[]
  urls: { assigned: string; started: string }
}

/** workflows::Start/Show */
export interface StartShowProps extends PageProps {
  workflow: { key: string; name: string; description: string | null }
  subject: { type: string; id: string | number; label: string } | null
  form: StencilSchema | null
  urls: { store: string; back: string }
}
