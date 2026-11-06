import type { InstanceData, Strings, WorkflowData } from "../types"

const COLORS: Record<string, string> = {
  success: "bg-green-100 text-green-800",
  warning: "bg-amber-100 text-amber-800",
  info: "bg-sky-100 text-sky-800",
  secondary: "bg-muted text-muted-foreground",
}

export function Badge({ label, color }: { label: string; color: string }): React.JSX.Element {
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${COLORS[color] ?? COLORS.secondary}`}>
      {label}
    </span>
  )
}

/** A workflow's status: published, draft, unpublished changes; locked; inactive */
export function WorkflowStatus({ workflow, strings }: { workflow: WorkflowData; strings: Strings }): React.JSX.Element {
  const color = workflow.status === "published" ? "success" : workflow.status === "changed" ? "warning" : "secondary"
  return (
    <span className="inline-flex flex-wrap items-center gap-1">
      <Badge label={workflow.status_label} color={color} />
      {workflow.is_locked ? <span className="text-muted-foreground text-xs">{strings.locked}</span> : null}
      {!workflow.is_active ? <span className="text-muted-foreground text-xs">{strings.inactive}</span> : null}
    </span>
  )
}

/** An instance's status, and its outcome once it finished */
export function InstanceStatus({ instance }: { instance: InstanceData }): React.JSX.Element {
  const color = instance.status === "completed" ? "success" : instance.status === "cancelled" ? "secondary" : "info"
  return (
    <span className="inline-flex flex-wrap items-center gap-1">
      <Badge label={instance.status_label} color={color} />
      {instance.outcome_label ? (
        <span className="text-sm" dir="auto">
          {instance.outcome_label}
        </span>
      ) : null}
    </span>
  )
}
