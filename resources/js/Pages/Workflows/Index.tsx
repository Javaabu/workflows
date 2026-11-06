import { Alert, Button, LinkButton } from "@javaabu/forms"
import { AssistantCard } from "../../components/AssistantCard"
import { WorkflowStatus } from "../../components/Badges"
import { PageHeader } from "../../components/PageHeader"
import { PaginationNav } from "../../components/PaginationNav"
import { StatusAlert } from "../../components/StatusAlert"
import type { WorkflowsIndexProps } from "../../types"

/** workflows::Workflows/Index — every workflow, with search */
export default function Index({
  workflows,
  pagination,
  search,
  can,
  urls,
  status,
  strings,
}: WorkflowsIndexProps): React.JSX.Element {
  return (
    <div className="flex flex-col gap-6" data-workflows-index="">
      <PageHeader
        title={strings.title ?? "Workflows"}
        description={strings.description}
        actions={
          urls.create && can.create ? (
            <LinkButton url={urls.create} startIcon="plus">
              {strings.create}
            </LinkButton>
          ) : null
        }
      />

      <StatusAlert status={status} />

      <form method="GET" action={urls.index} className="flex flex-wrap items-end gap-2" role="search">
        <div className="flex min-w-64 flex-col gap-1">
          <label htmlFor="workflows-search" className="text-sm font-medium">
            {strings.search}
          </label>
          <input
            id="workflows-search"
            type="search"
            name="search"
            defaultValue={search}
            dir="auto"
            className="bg-background h-9 rounded-md border px-3"
          />
        </div>
        <Button type="submit" color="outline-secondary">
          {strings.search_button}
        </Button>
      </form>

      {urls.assistant ? (
        <AssistantCard
          url={urls.assistant}
          title={strings.assistant ?? ""}
          help={strings.assistant_help ?? ""}
          strings={strings}
        />
      ) : null}

      {workflows.length === 0 ? (
        <Alert type="info">{search !== "" ? strings.no_results : strings.empty}</Alert>
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr>
                  <th scope="col" className="px-3 py-2 text-start font-medium">
                    {strings.name}
                  </th>
                  <th scope="col" className="px-3 py-2 text-start font-medium">
                    {strings.status}
                  </th>
                  <th scope="col" className="px-3 py-2 text-start font-medium">
                    {strings.version}
                  </th>
                  <th scope="col" className="px-3 py-2 text-start font-medium">
                    {strings.active}
                  </th>
                  <th scope="col" className="px-3 py-2 text-end font-medium">
                    <span className="sr-only">{strings.actions}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {workflows.map((workflow) => (
                  <tr key={workflow.key} className="border-t" data-workflow-key={workflow.key}>
                    <td className="px-3 py-2">
                      <a href={workflow.urls.show} className="font-medium hover:underline" dir="auto">
                        {workflow.name}
                      </a>
                      <div className="text-muted-foreground text-xs" dir="ltr">
                        {workflow.key}
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <WorkflowStatus workflow={workflow} strings={strings} />
                    </td>
                    <td className="px-3 py-2">{workflow.published_version ?? "—"}</td>
                    <td className="px-3 py-2">
                      {workflow.urls.instances ? (
                        <a href={workflow.urls.instances} className="hover:underline">
                          {workflow.active_count}
                        </a>
                      ) : (
                        workflow.active_count
                      )}
                    </td>
                    <td className="px-3 py-2 text-end">
                      {workflow.urls.show ? (
                        <LinkButton url={workflow.urls.show} color="outline-secondary" size="sm">
                          {strings.open_builder}
                        </LinkButton>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <PaginationNav pagination={pagination} strings={strings} />
        </>
      )}
    </div>
  )
}
