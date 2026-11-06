import { LinkButton } from "@javaabu/forms"
import type { InstanceData, Strings } from "../types"
import { InstanceStatus } from "./Badges"

/** Instances or tasks in a table */
export function InstancesTable({
  items,
  strings,
  showWorkflow = true,
}: {
  items: InstanceData[]
  strings: Strings
  showWorkflow?: boolean
}): React.JSX.Element {
  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50">
          <tr>
            <th scope="col" className="px-3 py-2 text-start font-medium">
              {strings.subject}
            </th>
            {showWorkflow ? (
              <th scope="col" className="px-3 py-2 text-start font-medium">
                {strings.workflow}
              </th>
            ) : null}
            <th scope="col" className="px-3 py-2 text-start font-medium">
              {strings.step}
            </th>
            <th scope="col" className="px-3 py-2 text-start font-medium">
              {strings.status}
            </th>
            <th scope="col" className="px-3 py-2 text-start font-medium">
              {strings.started_by}
            </th>
            <th scope="col" className="px-3 py-2 text-start font-medium">
              {strings.due}
            </th>
            <th scope="col" className="px-3 py-2 text-end font-medium">
              <span className="sr-only">{strings.actions}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {items.map((instance) => (
            <tr key={instance.id} className="border-t" data-instance-id={instance.id}>
              <td className="px-3 py-2" dir="auto">
                {instance.subject?.label ?? instance.title}
              </td>
              {showWorkflow ? (
                <td className="px-3 py-2" dir="auto">
                  {instance.workflow}
                </td>
              ) : null}
              <td className="px-3 py-2" dir="auto">
                {instance.step_name ?? "—"}
              </td>
              <td className="px-3 py-2">
                <InstanceStatus instance={instance} />
              </td>
              <td className="px-3 py-2" dir="auto">
                {instance.starter ?? "—"}
              </td>
              <td className="px-3 py-2">
                {instance.due_at_label ? (
                  <span className={instance.overdue ? "text-destructive font-medium" : undefined}>
                    {instance.due_at_label}
                    {instance.overdue ? <span className="sr-only"> ({strings.overdue})</span> : null}
                  </span>
                ) : (
                  "—"
                )}
              </td>
              <td className="px-3 py-2 text-end">
                {instance.url ? (
                  <LinkButton url={instance.url} color="outline-secondary" size="sm">
                    {strings.open}
                  </LinkButton>
                ) : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
