// Vite bundles the package styles with every page that uses them
import "../../../css/workflows.css"
import { Alert, LinkButton } from "@javaabu/forms"
import { InstancesTable } from "../../components/InstancesTable"
import { PageHeader } from "../../components/PageHeader"
import { PaginationNav } from "../../components/PaginationNav"
import { StatusAlert } from "../../components/StatusAlert"
import type { TasksIndexProps } from "../../types"

/** workflows::Tasks/Index — what's waiting for me, what I started, and what I can start */
export default function Index({
  items,
  view,
  pagination,
  startable,
  urls,
  status,
  strings,
}: TasksIndexProps): React.JSX.Element {
  const tab = (active: boolean) =>
    `border-b-2 px-3 py-2 text-sm ${active ? "border-primary font-medium" : "text-muted-foreground border-transparent"}`

  return (
    <div className="flex flex-col gap-6" data-workflows-tasks="">
      <PageHeader title={strings.tasks ?? "My tasks"} description={strings.tasks_description} />
      <StatusAlert status={status} />

      <nav className="flex gap-2 border-b" aria-label={strings.tasks}>
        <a
          href={urls.assigned}
          className={tab(view === "assigned")}
          aria-current={view === "assigned" ? "page" : undefined}
        >
          {strings.assigned_to_me}
        </a>
        <a
          href={urls.started}
          className={tab(view === "started")}
          aria-current={view === "started" ? "page" : undefined}
        >
          {strings.started_by_me}
        </a>
      </nav>

      {items.length === 0 ? (
        <Alert type="info">{view === "started" ? strings.no_started : strings.no_tasks}</Alert>
      ) : (
        <>
          <InstancesTable items={items} strings={strings} />
          <PaginationNav pagination={pagination} strings={strings} />
        </>
      )}

      {startable.length > 0 ? (
        <section aria-labelledby="workflows-start" className="flex flex-col gap-3">
          <h2 id="workflows-start" className="text-lg font-semibold">
            {strings.start_something}
          </h2>
          <ul className="grid gap-3 md:grid-cols-3">
            {startable.map((workflow) => (
              <li key={workflow.key} className="flex flex-col gap-2 rounded-md border p-3">
                <span className="font-medium" dir="auto">
                  {workflow.name}
                </span>
                {workflow.description ? (
                  <span className="text-muted-foreground text-sm" dir="auto">
                    {workflow.description}
                  </span>
                ) : null}
                <div>
                  <LinkButton url={workflow.url} size="sm">
                    {strings.start}
                  </LinkButton>
                </div>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  )
}
